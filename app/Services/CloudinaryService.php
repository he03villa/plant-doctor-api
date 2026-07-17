<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private string $cloudName = '';
    private string $apiKey = '';
    private string $apiSecret = '';
    private string $baseUrl;

    public function __construct()
    {
        $this->cloudName = config('services.cloudinary.cloud_name') ?? '';
        $this->apiKey = config('services.cloudinary.api_key') ?? '';
        $this->apiSecret = config('services.cloudinary.api_secret') ?? '';
        $this->baseUrl = "https://api.cloudinary.com/v1_1/{$this->cloudName}";
    }

    public function isConfigured(): bool
    {
        return !empty($this->cloudName) && !empty($this->apiKey) && !empty($this->apiSecret);
    }

    public function upload(UploadedFile $file, string $folder = 'plant-doctor'): ?string
    {
        if (!$this->isConfigured()) {
            Log::warning('Cloudinary not configured, falling back to local storage');
            return $file->store($folder, 'public');
        }

        try {
            $response = Http::attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post("{$this->baseUrl}/image/upload", [
                    'upload_preset' => 'plant-doctor',
                    'folder' => $folder,
                    'api_key' => $this->apiKey,
                ]);

            if ($response->successful()) {
                return $response->json('secure_url');
            }

            Log::error('Cloudinary upload failed', ['response' => $response->json()]);
            return $file->store($folder, 'public');
        } catch (\Exception $e) {
            Log::error('Cloudinary upload exception', ['message' => $e->getMessage()]);
            return $file->store($folder, 'public');
        }
    }

    public function delete(string $url): bool
    {
        if (!$this->isConfigured() || str_starts_with($url, 'plants/') || str_starts_with($url, 'diagnoses/')) {
            return false;
        }

        try {
            $publicId = $this->extractPublicId($url);
            if (!$publicId) {
                return false;
            }

            $timestamp = now()->timestamp;
            $signature = $this->generateSignature($publicId, $timestamp);

            $response = Http::post("{$this->baseUrl}/image/destroy", [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
                'api_key' => $this->apiKey,
                'signature' => $signature,
            ]);

            return $response->successful() && $response->json('result') === 'ok';
        } catch (\Exception $e) {
            Log::error('Cloudinary delete exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    private function extractPublicId(string $url): ?string
    {
        if (preg_match('#/upload/(?:v\d+/)?(.+?)(?:\.\w+)?$#', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function generateSignature(string $publicId, int $timestamp): string
    {
        $params = "public_id={$publicId}&timestamp={$timestamp}{$this->apiSecret}";
        return sha1($params);
    }
}
