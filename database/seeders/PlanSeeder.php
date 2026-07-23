<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_monthly' => 0,
                'price_yearly' => null,
                'features' => [
                    'max_products' => 20,
                    'max_users' => 1,
                    'has_map' => true,
                    'has_invoicing' => false,
                    'has_advanced_dashboard' => false,
                    'has_multi_store' => false,
                ],
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_monthly' => 9.99,
                'price_yearly' => 99.99,
                'features' => [
                    'max_products' => -1,
                    'max_users' => 5,
                    'has_map' => true,
                    'has_invoicing' => true,
                    'has_advanced_dashboard' => true,
                    'has_multi_store' => false,
                ],
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price_monthly' => 29.99,
                'price_yearly' => 299.99,
                'features' => [
                    'max_products' => -1,
                    'max_users' => -1,
                    'has_map' => true,
                    'has_invoicing' => true,
                    'has_advanced_dashboard' => true,
                    'has_multi_store' => true,
                ],
                'is_active' => true,
                'display_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
