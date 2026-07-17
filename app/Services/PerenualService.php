<?php

namespace App\Services;

use App\Models\Disease;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PerenualService
{
    private string $apiKey = '';
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.perenual.key') ?? '';
        $this->baseUrl = config('services.perenual.url') ?? 'https://perenual.com/api';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getDiseases(int $page = 1): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/diseases", [
                'key' => $this->apiKey,
                'page' => $page,
            ]);

            if ($response->successful()) {
                return $response->json('data', []);
            }

            Log::error('Perenual diseases API error', ['status' => $response->status()]);
            return [];
        } catch (\Exception $e) {
            Log::error('Perenual diseases API exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    public function getDiseaseDetails(int $perenualId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/diseases/details/{$perenualId}", [
                'key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Perenual disease details exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function syncDiseases(): array
    {
        $synced = 0;
        $created = 0;
        $updated = 0;
        $page = 1;

        while (true) {
            $diseases = $this->getDiseases($page);

            if (empty($diseases)) {
                break;
            }

            foreach ($diseases as $apiDisease) {
                $perenualId = $apiDisease['id'] ?? null;
                if (!$perenualId) {
                    continue;
                }

                $diseaseData = $this->mapDiseaseData($apiDisease);

                $disease = Disease::where('perenual_id', $perenualId)->first();

                if ($disease) {
                    $disease->update($diseaseData);
                    $updated++;
                } else {
                    Disease::create(array_merge($diseaseData, [
                        'perenual_id' => $perenualId,
                        'last_synced_at' => now(),
                    ]));
                    $created++;
                }

                $synced++;
                usleep(100000);
            }

            $page++;

            if ($page > 3) {
                break;
            }

            usleep(200000);
        }

        Disease::whereNull('last_synced_at')
            ->where('perenual_id', '!=', null)
            ->update(['last_synced_at' => now()]);

        return [
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function mapDiseaseData(array $apiDisease): array
    {
        $description = $apiDisease['description'] ?? null;
        $symptoms = $apiDisease['symptom'] ?? $apiDisease['symptoms'] ?? null;
        $treatment = $apiDisease['chemical'] ?? $apiDisease['biological'] ?? null;
        $prevention = $apiDisease['prevention'] ?? null;

        return [
            'name' => $apiDisease['name'] ?? 'Unknown Disease',
            'scientific_name' => $apiDisease['scientific_name'] ?? null,
            'description' => is_array($description) ? implode("\n", $description) : $description,
            'symptoms' => is_array($symptoms) ? implode("\n", $symptoms) : $symptoms,
            'treatment' => is_array($treatment) ? implode("\n", $treatment) : $treatment,
            'prevention' => is_array($prevention) ? implode("\n", $prevention) : $prevention,
            'image_path' => $apiDisease['image'] ?? null,
            'category' => $apiDisease['family'] ?? null,
            'severity' => 'medium',
            'is_active' => true,
            'last_synced_at' => now(),
        ];
    }
}
