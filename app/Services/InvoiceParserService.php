<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InvoiceParserService
{
    public function __construct(
        private GroqService $groqService
    ) {}

    public function parse(string $rawText): array
    {
        $result = $this->parseWithRegex($rawText);

        if ($this->hasValidItems($result)) {
            return $result;
        }

        return $this->parseWithGroq($rawText);
    }

    private function parseWithRegex(string $text): array
    {
        $result = [
            'invoice_number' => null,
            'invoice_date' => null,
            'supplier_name' => null,
            'items' => [],
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'currency' => 'COP',
        ];

        if (preg_match('/(?:factura|invoice|fact\.?|no\.?|#)\s*[:\-]?\s*(\d[\d\-\.]*)/i', $text, $m)) {
            $result['invoice_number'] = trim($m[1]);
        }

        if (preg_match('/(?:fecha|date|fecha\s+de\s+emisi[oó]n)\s*[:\-]?\s*(\d{1,2}[\s\/\-\.]\w+[\s\/\-\.]\d{2,4})/i', $text, $m)) {
            $result['invoice_date'] = $this->parseDate($m[1]);
        }

        if (preg_match('/(?:proveedor|supplier|vendedor|raz[oó]n\s+social|nombre)\s*[:\-]?\s*(.+?)(?:\n|$)/i', $text, $m)) {
            $result['supplier_name'] = trim($m[1]);
        }

        $result['items'] = $this->parseItems($text);

        if (preg_match('/(?:subtotal|sub\s*total|base\s+gravable)\s*[:\-]?\s*\$?\s*([\d\.\,]+)/i', $text, $m)) {
            $result['subtotal'] = $this->parseNumber($m[1]);
        }

        if (preg_match('/(?:iva|impuesto|tax|impto)\s*[:\-]?\s*\$?\s*([\d\.\,]+)/i', $text, $m)) {
            $result['tax'] = $this->parseNumber($m[1]);
        }

        if (preg_match('/(?:total\s+a\s+pagar|total\s+general|total|gran\s+total)\s*[:\-]?\s*\$?\s*([\d\.\,]+)/i', $text, $m)) {
            $result['total'] = $this->parseNumber($m[1]);
        }

        if (preg_match('/(USD|EUR|COP|COP|ARS|MXL|MXN)/i', $text, $m)) {
            $result['currency'] = strtoupper($m[1]);
        }

        return $result;
    }

    private function parseItems(string $text): array
    {
        $items = [];

        $patterns = [
            '/(\d+)\s+x\s+\$?\s*([\d\.\,]+)\s+(.+?)(?:\s+\$?\s*([\d\.\,]+))?$/m',
            '/(.+?)\s+(\d+)\s+(?:u|und|pcs?)\s+\$?\s*([\d\.\,]+)\s+\$?\s*([\d\.\,]+)/im',
            '/(?:^|\n)\s*(.+?)\s{2,}(\d+)\s+\$?\s*([\d\.\,]+)\s+\$?\s*([\d\.\,]+)/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $item = $this->classifyItemMatch($match);
                    if ($item) {
                        $items[] = $item;
                    }
                }
                if (!empty($items)) {
                    break;
                }
            }
        }

        return $items;
    }

    private function classifyItemMatch(array $match): ?array
    {
        $count = count($match);

        if ($count >= 5 && is_numeric($match[1])) {
            return [
                'product_name' => trim($match[3]),
                'quantity' => (int) $match[1],
                'unit_price' => $this->parseNumber($match[2]),
                'total_price' => !empty($match[4]) ? $this->parseNumber($match[4]) : 0,
            ];
        }

        if ($count >= 5 && is_numeric($match[2])) {
            return [
                'product_name' => trim($match[1]),
                'quantity' => (int) $match[2],
                'unit_price' => $this->parseNumber($match[3]),
                'total_price' => $this->parseNumber($match[4]),
            ];
        }

        return null;
    }

    private function parseWithGroq(string $rawText): array
    {
        $default = [
            'invoice_number' => null,
            'invoice_date' => null,
            'supplier_name' => null,
            'items' => [],
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'currency' => 'COP',
        ];

        if (!$this->groqService->isConfigured()) {
            Log::warning('Groq not configured for invoice parsing');
            return $default;
        }

        try {
            $prompt = $this->buildParsePrompt($rawText);
            $response = $this->callGroq($prompt);

            if ($response) {
                return $this->normalizeParsedData($response, $default);
            }
        } catch (\Exception $e) {
            Log::error('Groq invoice parse failed', ['message' => $e->getMessage()]);
        }

        return $default;
    }

    private function buildParsePrompt(string $rawText): string
    {
        return <<<PROMPT
Eres un experto en facturación colombiana y latinoamericana. Extrae los datos estructurados de esta factura.

Texto OCR de la factura:
---
{$rawText}
---

Responde ÚNICAMENTE con un JSON válido con esta estructura:
{
  "invoice_number": "número de factura o null",
  "invoice_date": "YYYY-MM-DD o null",
  "supplier_name": "nombre del proveedor o null",
  "items": [
    {
      "product_name": "nombre del producto",
      "quantity": cantidad_numerica,
      "unit_price": precio_unitario_numerico,
      "total_price": total_item_numerico
    }
  ],
  "subtotal": numerico,
  "tax": numerico,
  "total": numerico,
  "currency": "COP o USD o EUR"
}

Si un campo no se puede determinar, usa null para strings y 0 para números.
Para la fecha, usa formato YYYY-MM-DD.
Sé preciso con los números: elimina puntos de miles y usa decimal con punto.
PROMPT;
    }

    private function callGroq(string $prompt): ?array
    {
        $apiKey = config('services.groq.api_key') ?? '';
        $baseUrl = config('services.groq.url') ?? 'https://api.groq.com/openai/v1';
        $model = config('services.groq.model') ?? 'meta-llama/llama-4-scout-17b-16e-instruct';

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.1,
                'max_completion_tokens' => 1024,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->successful()) {
            $choices = $response->json('choices', []);
            if (!empty($choices[0]['message']['content'])) {
                return json_decode($choices[0]['message']['content'], true);
            }
        }

        return null;
    }

    private function normalizeParsedData(array $data, array $default): array
    {
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $items[] = [
                'product_name' => $item['product_name'] ?? 'Sin nombre',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? 0),
            ];
        }

        return [
            'invoice_number' => $data['invoice_number'] ?? $default['invoice_number'],
            'invoice_date' => $this->parseDate($data['invoice_date'] ?? ''),
            'supplier_name' => $data['supplier_name'] ?? $default['supplier_name'],
            'items' => $items,
            'subtotal' => (float) ($data['subtotal'] ?? 0),
            'tax' => (float) ($data['tax'] ?? 0),
            'total' => (float) ($data['total'] ?? 0),
            'currency' => strtoupper($data['currency'] ?? 'COP'),
        ];
    }

    private function parseDate(string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $dateStr = trim($dateStr);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }

        $formats = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    private function parseNumber(string $str): float
    {
        $str = trim($str);
        $str = preg_replace('/[^\d\.\,]/', '', $str);

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $str)) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (preg_match('/^\d+,\d{1,2}$/', $str)) {
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(',', '', $str);
        }

        return (float) $str;
    }

    private function hasValidItems(array $result): bool
    {
        return count($result['items']) > 0
            || $result['total'] > 0
            || !empty($result['invoice_number']);
    }
}
