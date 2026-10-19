<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class SetupStripePlans extends Command
{
    protected $signature = 'stripe:setup-plans';
    protected $description = 'Create Stripe Products/Prices idempotently, update .env and sync plans to DB';

    public function handle(): int
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
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

        $envPath = base_path('.env');
        $envContent = is_readable($envPath) ? (string) file_get_contents($envPath) : '';

        $envUpdates = [];

        foreach ($plans as $slug => $plan) {
            $this->newLine();
            $this->info("Procesando {$plan['name']}...");

            try {
                $existing = $this->productsForSlug($slug);

                $product = $this->resolveKeepProduct($slug, $existing, $envContent);

                if (! $product) {
                    $product = Product::create([
                        'name' => $plan['name'],
                        'description' => "{$plan['name']} for Plant Doctor viveros",
                        'metadata' => ['plan_slug' => $slug],
                    ]);
                    $this->line("  Producto creado: {$product->id}");
                } else {
                    $this->line("  Producto reutilizado: {$product->id}");
                }

                // Archivar duplicados con el mismo plan_slug (auto-limpieza).
                foreach ($existing as $duplicate) {
                    if ($duplicate->id === $product->id) {
                        continue;
                    }
                    $this->archiveProduct($duplicate);
                    $this->line("  Duplicado archivado: {$duplicate->id}");
                }

                $monthly = $this->ensurePrice($product, 'month', $plan['monthly'], $slug, 'monthly');
                $yearly = $this->ensurePrice($product, 'year', $plan['yearly'], $slug, 'yearly');

                $envUpdates['STRIPE_'.strtoupper($slug).'_PRICE_MONTHLY'] = $monthly->id;
                $envUpdates['STRIPE_'.strtoupper($slug).'_PRICE_YEARLY'] = $yearly->id;
            } catch (Exception $e) {
                $this->error("  Error con {$plan['name']}: {$e->getMessage()}");
                return 1;
            }
        }

        $this->newLine();
        $this->info('Actualizando .env...');

        foreach ($envUpdates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        if (is_writable($envPath)) {
            if (file_put_contents($envPath, $envContent) === false) {
                $this->warn('  No se pudo escribir el .env.');
            } else {
                $this->line('  .env actualizado');
            }
        } else {
            $this->warn('  El .env es de solo lectura (montado con :ro en docker-compose). Agregá estas líneas a mano en el .env del host:');
            foreach ($envUpdates as $key => $value) {
                $this->line("    {$key}={$value}");
            }
        }

        // Hacer visibles los valores en este proceso: env() se carga al arrancar
        // y el .env recién escrito no se vuelve a leer, así que inyectamos
        // los valores para que PlanSeeder los vea aunque haya config cacheada.
        foreach ($envUpdates as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $this->info('Sincronizando planes en la BD (PlanSeeder)...');
        $this->call('db:seed', ['--class' => 'PlanSeeder', '--force' => true]);

        $this->newLine();
        $this->info('Listo. Precios sincronizados:');
        foreach ($envUpdates as $key => $value) {
            $this->line("  {$key}={$value}");
        }

        return 0;
    }

    /**
     * Productos activos cuya metadata plan_slug coincide, del más antiguo al más nuevo.
     */
    private function productsForSlug(string $slug): array
    {
        $products = [];

        foreach (Product::all(['limit' => 100])->data as $product) {
            if (($product->metadata['plan_slug'] ?? null) === $slug && ($product->active ?? false)) {
                $products[] = $product;
            }
        }

        usort($products, fn (Product $a, Product $b) => $a->created <=> $b->created);

        return $products;
    }

    /**
     * Producto a conservar: el que referencian los price IDs actuales del .env;
     * si no hay, el más antiguo. Devuelve null si no existe ninguno.
     */
    private function resolveKeepProduct(string $slug, array $existing, string $envContent): ?Product
    {
        if ($existing === []) {
            return null;
        }

        foreach (['MONTHLY', 'YEARLY'] as $period) {
            $pattern = '/^STRIPE_'.preg_quote(strtoupper($slug).'_PRICE_'.$period, '/').'=(\S+)/m';

            if (! preg_match($pattern, $envContent, $matches)) {
                continue;
            }

            try {
                $price = Price::retrieve($matches[1]);
            } catch (Exception $e) {
                continue;
            }

            foreach ($existing as $product) {
                if ($product->id === $price->product) {
                    return $product;
                }
            }
        }

        return $existing[0];
    }

    /**
     * Reutiliza el precio activo del intervalo indicado o lo crea si no existe.
     */
    private function ensurePrice(Product $product, string $interval, int $amount, string $slug, string $period): Price
    {
        $prices = Price::all([
            'product' => $product->id,
            'active' => true,
            'limit' => 100,
        ])->data;

        foreach ($prices as $price) {
            if (($price->recurring->interval ?? null) === $interval) {
                $this->line("  Precio {$period} reutilizado: {$price->id}");

                return $price;
            }
        }

        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => $amount,
            'currency' => strtolower(config('services.stripe.currency', 'usd')),
            'recurring' => ['interval' => $interval],
            'metadata' => ['plan_slug' => $slug, 'interval' => $period],
        ]);
        $this->line("  Precio {$period} creado: {$price->id}");

        return $price;
    }

    /**
     * Archiva un producto y todos sus precios activos.
     */
    private function archiveProduct(Product $product): void
    {
        Product::update($product->id, ['active' => false]);

        $prices = Price::all([
            'product' => $product->id,
            'active' => true,
            'limit' => 100,
        ])->data;

        foreach ($prices as $price) {
            Price::update($price->id, ['active' => false]);
        }
    }
}
