<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreProduct>
 */
class StoreProductFactory extends Factory
{
    protected $model = StoreProduct::class;

    public function definition(): array
    {
        $categories = ['planta', 'fertilizante', 'maceta', 'sustrato', 'herramienta', 'pesticida', 'otro'];
        $units = ['unidad', 'kg', 'litro', 'maceta', 'bandeja', 'metro'];

        return [
            'store_id' => Store::factory(),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement($categories),
            'sku' => strtoupper(fake()->bothify('####-??')),
            'sale_price' => fake()->randomFloat(2, 5000, 100000),
            'purchase_price' => fake()->randomFloat(2, 2000, 60000),
            'stock_quantity' => fake()->numberBetween(0, 200),
            'min_stock' => fake()->numberBetween(0, 20),
            'unit' => fake()->randomElement($units),
            'barcode' => fake()->numerify('#############'),
            'description' => fake()->sentence(10),
            'image_url' => null,
            'is_active' => true,
            'is_visible_on_map' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => fake()->numberBetween(1, 200),
        ]);
    }

    public function lowStock(int $threshold = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => fake()->numberBetween(0, $threshold),
        ]);
    }

    public function visibleOnMap(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible_on_map' => true,
        ]);
    }
}
