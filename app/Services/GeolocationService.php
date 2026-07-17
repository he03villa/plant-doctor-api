<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeolocationService
{
    private string $nominatimUrl = 'https://nominatim.openstreetmap.org/reverse';

    public function findLocationByCoordinates(float $lat, float $lon): array
    {
        $cacheKey = "geolocation_{$lat}_{$lon}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($lat, $lon) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'PlantDoctor/1.0 (plant-doctor-api)',
                ])->timeout(10)->get($this->nominatimUrl, [
                    'lat' => $lat,
                    'lon' => $lon,
                    'format' => 'json',
                    'zoom' => 10,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $address = $data['address'] ?? [];

                    return [
                        'country_id' => null,
                        'state_id' => null,
                        'city_id' => null,
                        'country_name' => $address['country'] ?? null,
                        'state_name' => $address['state'] ?? $address['region'] ?? null,
                        'city_name' => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Geolocation failed', ['error' => $e->getMessage()]);
            }

            return $this->getFallbackLocation();
        });
    }

    public function findNearbyPlants(float $lat, float $lon, int $radiusMeters = 5000): \Illuminate\Support\Collection
    {
        return \App\Models\Plant::whereRaw("
            ST_Distance(
                location::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
            ) <= ?
        ", [$lon, $lat, $radiusMeters])
            ->orderByRaw("
                ST_Distance(
                    location::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                )
            ", [$lon, $lat])
            ->get();
    }

    private function getFallbackLocation(): array
    {
        return [
            'country_id' => null,
            'state_id' => null,
            'city_id' => null,
            'country_name' => null,
            'state_name' => null,
            'city_name' => null,
        ];
    }
}
