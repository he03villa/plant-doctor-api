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
            '/(?:^|\n)\s*(\d{1,3})\s{2,}(.+?)\s{2,}\$?\s*([\d\.\,]{1,})$/m',
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

        if ($count === 4 && is_numeric($match[1])) {
            $name = trim($match[2]);
            if (!preg_match('/[a-záéíóúñ]/i', $name)) {
                return null;
            }
            $quantity = max(1, (int) $match[1]);
            $totalPrice = $this->parseNumber($match[3]);
            return [
                'product_name' => $name,
                'quantity' => $quantity,
                'unit_price' => round($totalPrice / $quantity, 2),
                'total_price' => $totalPrice,
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
Eres un experto en facturación colombiana. Extrae datos estructurados de esta factura o recibo.

Texto OCR:
---
{$rawText}
---

TIPOS DE DOCUMENTO SOPORTADOS:
1. **Facturas comerciales** (productos, cantidades, precios unitarios)
2. **Recibos de servicios públicos** (energía, agua, aseo, gas) — NO tienen productos individuales
3. **Recibos de supermercado / almacén de cadena (POS)** (ej: Makro, PriceSmart, Éxito, Olímpica) — cada línea de producto debe extraerse como ítem

REGLAS PARA RECIBOS DE SERVICIOS PÚBLICOS:
- El proveedor suele aparecer después de "EMPRESA:" o es el nombre de la empresa en mayúsculas (ej: "ASEO TECNICO DE LA SABANA S.A.S.", "Air-e SAS ESP", "EPM", "EMCALI")
- El TOTAL REAL es el que aparece como "$165.440" o "TOTAL MES: $42.022". NO confundir con totales de secciones de desglose de tarifas (ej: "TOTAL: 60030.98" de la tabla de componentes tarifarios)
- Las fechas suelen estar en formato DD/MM/YYYY o DD/MM/YYYY HH:MM:SS
- El número de factura puede ser un número largo como "67190726" o "1029643"
- Para servicios públicos, crea UN SOLO ítem descriptivo con el concepto del servicio

REGLAS PARA RECIBOS DE SUPERMERCADO/ALMACÉN DE CADENA:
- Las líneas de producto aparecen en formatos como:
  * "2 ARROZ DIANA 500G 4.500" → cantidad, nombre, total
  * "TOMATE CHONTO 1.000 Kg 1.580" → nombre, cantidad, unidad, total
  * "7709876 PAN MOLDE 12.900" → (código de barras opcional) nombre, total
- Unidades frecuentes: KGM (kilogramo), EA/UND/PCS (unidad), LT/L, GR, ML
- Si la línea tiene solo cantidad y total (sin precio unitario), calcula unit_price = total / cantidad
- Si no hay cantidad explícita, usa cantidad = 1
- NO incluyas como ítems: líneas de promociones ("La Jugada ganadora", sorteos), mensajes de bienvenida, "Vales Emitidos", "Tarjeta Déb/Créd", "Articulos Vendidos", "CAJA", "ATENDIDO POR", números de transacción
- El proveedor suele ser el nombre del establecimiento; si no aparece claramente usa null

FORMATO DE NÚMEROS COLOMBIANOS:
- "$165.440" = 165440 (punto es separador de miles)
- "$1.234.567" = 1234567
- "42.022" = 42022
- Si hay coma: "$165,440" = 165440 (también separador de miles en algunos contextos)
- El OCR puede insertar espacios entre cifras (ej: "67. 699"); únelos antes de interpretar el número

Responde ÚNICAMENTE con JSON válido:
{
  "invoice_number": "número de factura o null",
  "invoice_date": "YYYY-MM-DD o null",
  "supplier_name": "nombre exacto de la empresa proveedora",
  "items": [
    {
      "product_name": "nombre del servicio o producto",
      "quantity": 1,
      "unit_price": monto_total_numerico,
      "total_price": monto_total_numerico
    }
  ],
  "subtotal": numerico,
  "tax": numerico,
  "total": numerico_total_a_pagar,
  "currency": "COP"
}

Para servicios públicos sin items individuales, usa:
- product_name: "Servicio de [tipo] - [período]" (ej: "Servicio de energía - Junio 2026")
- quantity: 1
- unit_price y total_price: el monto total del recibo
- subtotal: subtotal si aparece, si no = total
- tax: impuestos si aparecen, si no = 0

Si un campo no se puede determinar, usa null para strings y 0 para números.
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

        Log::warning('Groq parse response not successful', [
            'status' => $response->status(),
            'body' => $response->json(),
            'model' => $model,
        ]);

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
            || (!empty($result['supplier_name']) && $result['total'] > 0);
    }
}
