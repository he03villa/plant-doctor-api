<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreService
{
    public function findNearbyWithProducts(
        float $lat,
        float $lng,
        int $radiusMeters = 5000,
        ?Collection $productNames = null,
        int $limit = 20
    ): array {
        $productNames = $productNames ?? collect();
        $genericNamesLower = $productNames->map(fn(string $name) => strtolower($name))->toArray();

        $stores = Store::active()
            ->whereRaw("
                ST_Distance(
                    location::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) <= ?
            ", [$lng, $lat, $radiusMeters])
            ->with(['storeProducts' => function ($query) {
                $query->active()->inStock();
            }])
            ->get()
            ->map(function ($store) use ($lat, $lng, $genericNamesLower) {
                $distance = (int) round(DB::select("
                    SELECT ST_Distance(
                        location::geography,
                        ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                    ) AS distance
                ", [$lng, $lat])[0]->distance);

                $store->distance_meters = $distance;

                return $store;
            })
            ->sortBy('distance_meters')
            ->values();

        $results = $stores->map(function ($store) use ($genericNamesLower, $productNames) {
            $matchingProducts = [];
            $matchedGenericIndexes = [];

            foreach ($genericNamesLower as $index => $genericName) {
                $genericOriginal = $productNames[$index];

                foreach ($store->storeProducts as $storeProduct) {
                    $storeProductNameLower = strtolower($storeProduct->name);

                    similar_text($genericName, $storeProductNameLower, $percent);
                    $similarity = $percent / 100;

                    if ($similarity >= 0.6) {
                        $matchingProducts[] = [
                            'generic_name' => $genericOriginal,
                            'store_product_name' => $storeProduct->name,
                            'price' => (float) $storeProduct->sale_price,
                        ];
                        $matchedGenericIndexes[] = $index;
                        break;
                    }
                }

                if (!in_array($index, $matchedGenericIndexes)) {
                    foreach ($store->storeProducts as $storeProduct) {
                        $storeProductNameLower = strtolower($storeProduct->name);

                        if (str_contains($storeProductNameLower, $genericName)
                            || str_contains($genericName, $storeProductNameLower)) {
                            $matchingProducts[] = [
                                'generic_name' => $genericOriginal,
                                'store_product_name' => $storeProduct->name,
                                'price' => (float) $storeProduct->sale_price,
                            ];
                            $matchedGenericIndexes[] = $index;
                            break;
                        }
                    }
                }
            }

            $missingProducts = [];
            foreach ($genericNamesLower as $index => $genericName) {
                if (!in_array($index, $matchedGenericIndexes)) {
                    $missingProducts[] = ['generic_name' => $productNames[$index]];
                }
            }

            return [
                'id' => $store->id,
                'name' => $store->name,
                'distance_meters' => $store->distance_meters,
                'has_recommended_products' => count($matchingProducts) > 0,
                'match_count' => count($matchingProducts),
                'matching_products' => $matchingProducts,
                'missing_products' => $missingProducts,
            ];
        });

        $withProducts = $results->filter(fn(array $s) => $s['has_recommended_products'])->values();
        $withoutProducts = $results->filter(fn(array $s) => !$s['has_recommended_products'])->values();

        $sorted = $withProducts->concat($withoutProducts)->take($limit);

        $totalNearby = $results->count();
        $totalWithProducts = $withProducts->count();

        return [
            'stores' => $sorted->values()->all(),
            'summary' => [
                'total_nearby' => $totalNearby,
                'total_with_products' => $totalWithProducts,
                'searched_products' => $productNames->values()->all(),
            ],
        ];
    }
}
