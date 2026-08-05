<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $total = fake()->randomFloat(2, 10000, 500000);

        return [
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
            'invoice_number' => 'V-'.strtoupper(fake()->unique()->bothify('######')),
            'subtotal' => $total,
            'tax' => 0,
            'total' => $total,
            'currency' => 'COP',
            'payment_method' => 'cash',
            'status' => 'completed',
            'notes' => null,
        ];
    }
}
