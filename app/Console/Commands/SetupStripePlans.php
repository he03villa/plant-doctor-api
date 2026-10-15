<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;
use Exception;

class SetupStripePlans extends Command
{
    protected $signature = 'stripe:setup-plans';
    protected $description = 'Create Stripe Products and Prices, then update .env';

    public function handle(): int
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            $this->error('STRIPE_SECRET is not configured in .env');
            return 1;
        }

        Stripe::setApiKey($secret);

        $plans = [
            'pro' => [
                'name' => 'Pro Plan',
                'monthly' => 999,   // $9.99 in cents
                'yearly' => 9999,   // $99.99 in cents
            ],
            'business' => [
                'name' => 'Business Plan',
                'monthly' => 2999,  // $29.99 in cents
                'yearly' => 29999,  // $299.99 in cents
            ],
        ];

        $envUpdates = [];

        foreach ($plans as $slug => $plan) {
            $this->newLine();
            $this->info("Creating {$plan['name']} product...");

            try {
                $product = Product::create([
                    'name' => $plan['name'],
                    'description' => "{$plan['name']} for Plant Doctor viveros",
                    'metadata' => ['plan_slug' => $slug],
                ]);

                $this->line("  Product created: {$product->id}", 'green');

                // Monthly price
                $this->line("  Creating monthly price ($" . ($plan['monthly'] / 100) . ")...");
                $monthlyPrice = Price::create([
                    'product' => $product->id,
                    'unit_amount' => $plan['monthly'],
                    'currency' => strtolower(config('services.stripe.currency', 'usd')),
                    'recurring' => ['interval' => 'month'],
                    'metadata' => ['plan_slug' => $slug, 'interval' => 'monthly'],
                ]);
                $this->line("  Monthly price: {$monthlyPrice->id}", 'green');

                $envKey = strtoupper($slug) . '_PRICE_MONTHLY';
                $envUpdates[$envKey] = $monthlyPrice->id;

                // Yearly price
                $this->line("  Creating yearly price ($" . ($plan['yearly'] / 100) . ")...");
                $yearlyPrice = Price::create([
                    'product' => $product->id,
                    'unit_amount' => $plan['yearly'],
                    'currency' => strtolower(config('services.stripe.currency', 'usd')),
                    'recurring' => ['interval' => 'year'],
                    'metadata' => ['plan_slug' => $slug, 'interval' => 'yearly'],
                ]);
                $this->line("  Yearly price: {$yearlyPrice->id}", 'green');

                $envKey = strtoupper($slug) . '_PRICE_YEARLY';
                $envUpdates[$envKey] = $yearlyPrice->id;

            } catch (Exception $e) {
                $this->error("  Error creating {$plan['name']}: {$e->getMessage()}");
                return 1;
            }
        }

        $this->newLine();
        $this->info('Updating .env file...');

        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($envUpdates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $envContent);
        $this->line('  .env updated', 'green');

        $this->info('Running PlanSeeder...');
        $this->call('db:seed', ['--class' => 'PlanSeeder']);

        $this->newLine();
        $this->info('Done! Prices created and synced:');
        foreach ($envUpdates as $key => $value) {
            $this->line("  {$key}={$value}");
        }

        return 0;
    }
}
