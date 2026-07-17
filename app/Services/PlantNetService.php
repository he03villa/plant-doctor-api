<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlantNetService
{
    private string $apiKey = '';
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.plantnet.key') ?? '';
        $this->baseUrl = config('services.plantnet.url') ?? 'https://api.plantnet.org/v2/identify/all';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function identify(string $imageUrl, string $organ = 'leaf'): array
    {
        if (!$this->isConfigured()) {
            Log::warning('Pl@ntNet API not configured, returning mock result');
            return [
                'species' => null,
                'common_names' => [],
                'genus' => null,
                'family' => null,
                'score' => 0,
                'disease_id' => null,
                'confidence' => 0,
                'suggestions' => [],
            ];
        }

        try {
            $response = Http::timeout(30)
                ->attach('images', file_get_contents($imageUrl), 'plant-image.jpg')
                ->post($this->baseUrl . '?api-key=' . $this->apiKey, [
                    'organs' => $organ,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->parseResponse($data);
            }

            Log::error('Pl@ntNet API error', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return $this->getDefaultResult();
        } catch (\Exception $e) {
            Log::error('Pl@ntNet API exception', ['message' => $e->getMessage()]);
            return $this->getDefaultResult();
        }
    }

    private function parseResponse(array $data): array
    {
        $results = $data['results'] ?? [];

        if (empty($results)) {
            return $this->getDefaultResult();
        }

        $best = $results[0];
        $species = $best['species'] ?? [];

        return [
            'species' => $species['scientificNameWithoutAuthor'] ?? null,
            'common_names' => $species['commonNames'] ?? [],
            'genus' => $species['genus'] ?? null,
            'family' => $species['family'] ?? null,
            'score' => $best['score'] ?? 0,
            'disease_id' => null,
            'confidence' => round(($best['score'] ?? 0) * 100, 2),
            'suggestions' => array_map(fn($r) => [
                'species' => $r['species']['scientificNameWithoutAuthor'] ?? null,
                'common_names' => $r['species']['commonNames'] ?? [],
                'score' => $r['score'] ?? 0,
            ], array_slice($results, 0, 5)),
        ];
    }

    private function getDefaultResult(): array
    {
        return [
            'species' => null,
            'common_names' => [],
            'genus' => null,
            'family' => null,
            'score' => 0,
            'disease_id' => null,
            'confidence' => 0,
            'suggestions' => [],
        ];
    }
}
