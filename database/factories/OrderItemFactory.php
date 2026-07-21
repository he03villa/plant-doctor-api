<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50);
        $unitPrice = fake()->randomFloat(2, 1000, 50000);

        return [
            'order_id' => Order::factory(),
            'product_name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => round($quantity * $unitPrice, 2),
            'matched_product_id' => null,
        ];
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'matched_product_id' => StoreProduct::factory(),
        ]);
    }
}
