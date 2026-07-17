<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    private string $apiKey = '';
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key') ?? '';
        $this->baseUrl = config('services.groq.url') ?? 'https://api.groq.com/openai/v1';
        $this->model = config('services.groq.model') ?? 'meta-llama/llama-4-scout-17b-16e-instruct';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function diagnose(string $imagePath, string $speciesContext = ''): array
    {
        if (!$this->isConfigured()) {
            Log::warning('Groq API not configured, returning default result');
            return $this->getDefaultResult();
        }

        if (!file_exists($imagePath)) {
            Log::error('Image file not found for Groq diagnosis', ['path' => $imagePath]);
            return $this->getDefaultResult();
        }

        try {
            $imageData = file_get_contents($imagePath);
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
            $base64Image = base64_encode($imageData);

            $prompt = $this->buildPrompt($speciesContext);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$base64Image}",
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'temperature' => 1,
                    'max_completion_tokens' => 1024,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->successful()) {
                return $this->parseResponse($response->json());
            }

            Log::error('Groq API error', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return $this->getDefaultResult();
        } catch (\Exception $e) {
            Log::error('Groq API exception', ['message' => $e->getMessage()]);
            return $this->getDefaultResult();
        }
    }

    private function buildPrompt(string $speciesContext): string
    {
        $speciesLine = $speciesContext
            ? "La especie identificada previamente es: {$speciesContext}."
            : '';

        return <<<PROMPT
Eres un fitopatólogo experto con 20 años de experiencia en diagnóstico de enfermedades de plantas.

Analiza esta imagen de una planta {$speciesLine}

Identifica si la planta muestra signos de enfermedad, plagas o deficiencias nutricionales. Observa manchas, decoloraciones, deformaciones, marchitamiento, o cualquier síntoma visible.

Responde ÚNICAMENTE con un JSON válido con esta estructura exacta:
{
  "has_disease": true o false,
  "disease_name": "nombre común de la enfermedad o condición detectada",
  "disease_name_scientific": "nombre científico si se conoce, o null",
  "severity": "low, medium o high",
  "confidence": número decimal entre 0.0 y 1.0,
  "symptoms_observed": ["síntoma1 observado", "síntoma2 observado"],
  "treatment": "recomendación específica de tratamiento",
  "prevention": "medidas de prevención recomendadas",
  "description": "descripción breve del diagnóstico en una oración"
}

Si la planta está sana y no muestra síntomas de enfermedad:
- has_disease: false
- disease_name: "Sana"
- confidence: 0.9 o superior
- treatment/prevention: "No se requiere tratamiento"

Sé específico y preciso en tu diagnóstico. Basa tu análisis en evidencia visual observable en la imagen.
PROMPT;
    }

    private function parseResponse(array $data): array
    {
        $choices = $data['choices'] ?? [];
        if (empty($choices)) {
            return $this->getDefaultResult();
        }

        $content = $choices[0]['message']['content'] ?? '';
        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            return $this->getDefaultResult();
        }

        return [
            'has_disease' => $parsed['has_disease'] ?? false,
            'disease_name' => $parsed['disease_name'] ?? null,
            'disease_name_scientific' => $parsed['disease_name_scientific'] ?? null,
            'severity' => $parsed['severity'] ?? 'low',
            'confidence' => round(($parsed['confidence'] ?? 0) * 100, 2),
            'symptoms_observed' => $parsed['symptoms_observed'] ?? [],
            'treatment' => $parsed['treatment'] ?? null,
            'prevention' => $parsed['prevention'] ?? null,
            'description' => $parsed['description'] ?? null,
        ];
    }

    private function getDefaultResult(): array
    {
        return [
            'has_disease' => false,
            'disease_name' => null,
            'disease_name_scientific' => null,
            'severity' => null,
            'confidence' => 0,
            'symptoms_observed' => [],
            'treatment' => null,
            'prevention' => null,
            'description' => null,
        ];
    }
}
