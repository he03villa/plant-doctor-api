<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10000, 500000);
        $tax = round($subtotal * 0.19, 2);

        return [
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
            'invoice_number' => fake()->bothify('####-####'),
            'invoice_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'supplier_name' => fake()->company(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'currency' => 'COP',
            'invoice_image_url' => null,
            'ocr_raw_text' => null,
            'ocr_confidence' => null,
            'status' => 'pending',
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'verified',
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
        ]);
    }

    public function withOcr(): static
    {
        return $this->state(fn (array $attributes) => [
            'ocr_raw_text' => fake()->paragraph(5),
            'ocr_confidence' => fake()->randomFloat(2, 0.5, 0.99),
        ]);
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_image_url' => fake()->imageUrl(),
        ]);
    }
}
