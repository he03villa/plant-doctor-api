<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $lat = fake()->latitude(-4, 12);
        $lng = fake()->longitude(-80, -65);

        return [
            'user_id' => User::factory(),
            'name' => fake()->company() . ' Vivero',
            'business_name' => fake()->company(),
            'tax_id' => fake()->bothify('########-#'),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'business_phone' => fake()->phoneNumber(),
            'business_email' => fake()->companyEmail(),
            'latitude' => $lat,
            'longitude' => $lng,
            'is_active' => true,
            'is_premium' => false,
            'onboarding_completed' => false,
            'sync_to_map' => false,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => true,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function onboarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_completed' => true,
        ]);
    }

    public function visibleOnMap(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_to_map' => true,
        ]);
    }
}
