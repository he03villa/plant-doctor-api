<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithStore(array $storeData = []): array
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('store_owner');

        $store = Store::factory()->create(array_merge(['user_id' => $user->id], $storeData));

        return [$user, $store];
    }

    private function product(Store $store, int $stock = 100): StoreProduct
    {
        return StoreProduct::factory()->create([
            'store_id' => $store->id,
            'sale_price' => 25000,
            'purchase_price' => 10000,
            'stock_quantity' => $stock,
        ]);
    }

    private function payload(StoreProduct $product, ?array $payments = null, int $quantity = 2, int $unitPrice = 25000): array
    {
        return [
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ],
            ],
            'payments' => $payments,
        ];
    }

    public function test_creates_sale_with_split_payments(): void
    {
        [$user, $store] = $this->ownerWithStore();
        $product = $this->product($store);

        $response = $this->actingAs($user, 'api')->postJson("/api/stores/{$store->id}/sales", $this->payload($product, [
            ['method' => 'cash', 'amount' => 30000],
            ['method' => 'card', 'amount' => 20000],
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.total', 50000);
        $response->assertJsonPath('data.payment_method', 'cash');
        $response->assertJsonPath('data.payments.0.method', 'cash');
        $response->assertJsonPath('data.payments.0.amount', 30000);
        $response->assertJsonPath('data.payments.1.method', 'card');
        $response->assertJsonPath('data.payments.1.amount', 20000);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $response->json('data.id'),
            'payment_method' => 'cash',
            'amount' => 30000,
        ]);
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $response->json('data.id'),
            'payment_method' => 'card',
            'amount' => 20000,
        ]);

        $this->assertDatabaseHas('store_products', [
            'id' => $product->id,
            'stock_quantity' => 98,
        ]);
    }

    public function test_rejects_sale_when_payments_do_not_match_total(): void
    {
        [$user, $store] = $this->ownerWithStore();
        $product = $this->product($store);

        $response = $this->actingAs($user, 'api')->postJson("/api/stores/{$store->id}/sales", $this->payload($product, [
            ['method' => 'cash', 'amount' => 10000],
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_creates_sale_with_legacy_payment_method(): void
    {
        [$user, $store] = $this->ownerWithStore();
        $product = $this->product($store);

        $payload = $this->payload($product, null);
        unset($payload['payments']);
        $payload['payment_method'] = 'card';

        $response = $this->actingAs($user, 'api')->postJson("/api/stores/{$store->id}/sales", $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.payment_method', 'card');
        $response->assertJsonCount(1, 'data.payments');
        $response->assertJsonPath('data.payments.0.method', 'card');
        $response->assertJsonPath('data.payments.0.amount', 50000);
    }

    public function test_creates_sale_with_default_cash_payment(): void
    {
        [$user, $store] = $this->ownerWithStore();
        $product = $this->product($store);

        $payload = $this->payload($product, null);
        unset($payload['payments']);

        $response = $this->actingAs($user, 'api')->postJson("/api/stores/{$store->id}/sales", $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.payment_method', 'cash');
        $response->assertJsonCount(1, 'data.payments');
        $response->assertJsonPath('data.payments.0.method', 'cash');
        $response->assertJsonPath('data.payments.0.amount', 50000);
    }

    public function test_rejects_sale_without_product_id(): void
    {
        [$user, $store] = $this->ownerWithStore();

        $response = $this->actingAs($user, 'api')->postJson("/api/stores/{$store->id}/sales", [
            'items' => [
                [
                    'product_name' => 'Producto sin inventario',
                    'quantity' => 1,
                    'unit_price' => 15000,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 15000],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_rejects_sale_with_insufficient_stock(): void
    {
        [$user, $store] = $this->ownerWithStore();
        $product = $this->product($store, stock: 1);

        $response = $this->actingAs($user, 'api')->postJson("/api/stores/{$store->id}/sales", $this->payload($product, [
            ['method' => 'cash', 'amount' => 50000],
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseCount('sales', 0);
    }
}
