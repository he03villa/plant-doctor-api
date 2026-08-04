<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function storeUser(): array
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        return [$user, $store];
    }

    private function createOrder(User $user, Store $store, string $status = 'pending'): Order
    {
        return Order::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'type' => 'proveedor',
            'status' => $status,
        ]);
    }

    public function test_pending_items_returns_grouped_unmatched_items(): void
    {
        [$user, $store] = $this->storeUser();

        $order = $this->createOrder($user, $store);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Fertilizante N.P.K.',
            'quantity' => 5,
            'created_at' => now()->subDays(3),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Abono 20-20',
            'quantity' => 2,
            'created_at' => now()->subDays(3),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Fertilizante N P K',
            'quantity' => 3,
            'created_at' => now()->subDay(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Ya Vinculado',
            'quantity' => 9,
            'matched_product_id' => StoreProduct::factory()->create(['store_id' => $store->id])->id,
        ]);

        $serviceOrder = Order::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'type' => 'servicio',
        ]);
        OrderItem::factory()->create([
            'order_id' => $serviceOrder->id,
            'product_name' => 'Sin Vincular',
            'quantity' => 7,
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/api/orders/pending-items');

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $groups = collect($response->json('data'));

        $fertilizante = $groups->firstWhere('total_quantity', 8);
        $this->assertNotNull($fertilizante);
        $this->assertEquals(2, $fertilizante['order_count']);
        $this->assertSame('Fertilizante N P K', $fertilizante['product_name']);

        $abono = $groups->firstWhere('total_quantity', 2);
        $this->assertNotNull($abono);
        $this->assertEquals(1, $abono['order_count']);

        $this->assertNull($groups->firstWhere('total_quantity', 9));
        $this->assertNull($groups->firstWhere('total_quantity', 7));
    }

    public function test_link_items_matches_by_normalized_name(): void
    {
        [$user, $store] = $this->storeUser();

        $product = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'name' => 'Abono 20-20',
            'stock_quantity' => 10,
        ]);

        $order = $this->createOrder($user, $store);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'abono 20/20',
            'quantity' => 4,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'ABONO 20-20',
            'quantity' => 2,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Otra Cosa',
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/orders/items/link', [
            'product_name' => 'ABONO 20/20',
            'product_id' => $product->id,
        ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.linked_count'));

        $this->assertSame(2, OrderItem::where('matched_product_id', $product->id)->count());
        $this->assertSame(1, OrderItem::where('matched_product_id', null)->count());
    }

    public function test_link_items_increments_stock_for_verified_orders(): void
    {
        [$user, $store] = $this->storeUser();

        $product = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'name' => 'Abono 20-20',
            'stock_quantity' => 10,
        ]);

        $order = $this->createOrder($user, $store, 'verified');

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Abono 20-20',
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/orders/items/link', [
            'product_name' => 'Abono 20-20',
            'product_id' => $product->id,
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.linked_count'));
        $this->assertSame(13, $product->fresh()->stock_quantity);
    }

    public function test_link_items_does_not_increment_stock_for_pending_orders(): void
    {
        [$user, $store] = $this->storeUser();

        $product = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'name' => 'Abono 20-20',
            'stock_quantity' => 10,
        ]);

        $order = $this->createOrder($user, $store, 'pending');

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Abono 20-20',
            'quantity' => 3,
        ]);

        $this->actingAs($user, 'api')->postJson('/api/orders/items/link', [
            'product_name' => 'Abono 20-20',
            'product_id' => $product->id,
        ])->assertOk();

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_link_items_returns_404_for_product_of_another_store(): void
    {
        [$user, $store] = $this->storeUser();

        $otherStore = Store::factory()->create();
        $otherProduct = StoreProduct::factory()->create(['store_id' => $otherStore->id]);

        $order = $this->createOrder($user, $store);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Abono 20-20',
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/orders/items/link', [
            'product_name' => 'Abono 20-20',
            'product_id' => $otherProduct->id,
        ]);

        $response->assertNotFound();
    }

    public function test_link_items_returns_422_when_fields_missing(): void
    {
        [$user, $store] = $this->storeUser();

        $this->actingAs($user, 'api')
            ->postJson('/api/orders/items/link', [])
            ->assertStatus(422);
    }

    public function test_pending_items_returns_404_when_user_has_no_store(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('/api/orders/pending-items')
            ->assertNotFound();
    }

    public function test_create_order_auto_matches_items_to_products(): void
    {
        [$user, $store] = $this->storeUser();

        $product = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'name' => 'Fertilizante NPK 20kg',
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/orders', [
            'supplier_name' => 'Distribuidora ABC',
            'subtotal' => 200,
            'total' => 200,
            'items' => [
                [
                    'product_name' => 'Fertilizante NPK',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'total_price' => 200,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.matched_product.id', $product->id);
    }
}
