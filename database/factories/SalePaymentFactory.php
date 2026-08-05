<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalePayment>
 */
class SalePaymentFactory extends Factory
{
    protected $model = SalePayment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'payment_method' => fake()->randomElement(['cash', 'card', 'transfer']),
        ];
    }
}
