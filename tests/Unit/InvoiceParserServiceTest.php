<?php

namespace Tests\Unit;

use App\Services\GroqService;
use App\Services\InvoiceParserService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceParserServiceTest extends TestCase
{
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.groq.api_key' => 'test-key',
            'services.groq.url' => 'https://api.groq.com/openai/v1',
            'services.groq.model' => 'openai/gpt-oss-120b',
        ]);
    }

    private function parse(string $ocrText): array
    {
        return app(InvoiceParserService::class)->parse($ocrText);
    }

    private function groqReturns(array $body, int $status = 200): void
    {
        Http::fake([
            self::GROQ_URL => Http::response($body, $status),
        ]);
    }

    private function groqReturnsContent(string $content, string $finishReason = 'stop'): void
    {
        $this->groqReturns([
            'choices' => [
                [
                    'message' => ['content' => $content],
                    'finish_reason' => $finishReason,
                ],
            ],
        ]);
    }

    public function test_parses_groq_json_into_structured_data(): void
    {
        $this->groqReturnsContent(json_encode([
            'invoice_number' => 'FV-2043',
            'invoice_date' => '2026-08-05',
            'supplier_name' => 'Aseo Tecnico de la Sabana S.A.S.',
            'items' => [
                [
                    'product_name' => 'Servicio de acueducto',
                    'quantity' => 1,
                    'unit_price' => 42022,
                    'total_price' => 42022,
                ],
            ],
            'subtotal' => 42022,
            'tax' => 0,
            'total' => 42022,
            'currency' => 'COP',
        ]));

        $result = $this->parse("texto sin estructura reconocible\nlinea dos");

        $this->assertNull($result['parse_warning']);
        $this->assertSame('FV-2043', $result['invoice_number']);
        $this->assertSame('2026-08-05', $result['invoice_date']);
        $this->assertSame('Aseo Tecnico de la Sabana S.A.S.', $result['supplier_name']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Servicio de acueducto', $result['items'][0]['product_name']);
        $this->assertSame(42022.0, $result['total']);
    }

    public function test_uses_the_configured_model(): void
    {
        config(['services.groq.model' => 'openai/gpt-oss-20b']);
        $this->groqReturnsContent('{}');

        $this->parse("texto sin estructura reconocible\nlinea dos");

        Http::assertSent(function (Request $request) {
            return $request['model'] === 'openai/gpt-oss-20b';
        });
    }

    public function test_reports_model_not_found_instead_of_generic_failure(): void
    {
        $this->groqReturns([
            'error' => [
                'message' => 'The model `llama-3.3-70b-versatile` does not exist or you do not have access to it.',
                'code' => 'model_not_found',
            ],
        ], 404);

        $result = $this->parse("texto sin estructura reconocible\nlinea dos");

        $this->assertSame(
            'El motor de IA no tiene acceso al modelo configurado. Revisa GROQ_MODEL.',
            $result['parse_warning']
        );
        $this->assertSame([], $result['items']);
    }

    public function test_does_not_retry_on_model_not_found(): void
    {
        $this->groqReturns([
            'error' => ['code' => 'model_not_found'],
        ], 404);

        $this->parse("texto sin estructura reconocible\nlinea dos");

        Http::assertSentCount(1);
    }

    public function test_reports_unauthorized_when_api_key_is_rejected(): void
    {
        $this->groqReturns([
            'error' => ['code' => 'invalid_api_key'],
        ], 401);

        $result = $this->parse("texto sin estructura reconocible\nlinea dos");

        $this->assertSame(
            'La clave del motor de IA fue rechazada. Revisa la configuración de GROQ_API_KEY.',
            $result['parse_warning']
        );
        Http::assertSentCount(1);
    }

    public function test_reports_invalid_json_when_content_is_not_an_object(): void
    {
        $this->groqReturnsContent('Aceptado');

        $result = $this->parse("texto sin estructura reconocible\nlinea dos");

        $this->assertSame(
            'El motor de IA devolvió una respuesta que no se pudo interpretar. Revisa el texto e inténtalo de nuevo.',
            $result['parse_warning']
        );
    }

    public function test_reports_empty_content_when_choice_has_no_message(): void
    {
        $this->groqReturns(['choices' => [['message' => ['content' => ''], 'finish_reason' => 'stop']]]);

        $result = $this->parse("texto sin estructura reconocible\nlinea dos");

        $this->assertSame(
            'El motor de IA devolvió una respuesta vacía. Revisa el texto del recibo e inténtalo de nuevo.',
            $result['parse_warning']
        );
    }

    public function test_reports_client_error_when_request_is_rejected(): void
    {
        $this->groqReturns(['error' => ['code' => 'bad_request']], 400);

        $result = $this->parse("texto sin estructura reconocible\nlinea dos");

        $this->assertSame(
            'El motor de IA rechazó la solicitud. Revisa el texto del recibo e inténtalo de nuevo.',
            $result['parse_warning']
        );
        Http::assertSentCount(1);
    }

    public function test_reports_rate_limited_after_exhausting_retries(): void
    {
        $this->groqReturns(['error' => ['code' => 'rate_limit_exceeded']], 429);

        $result = $this->parse("texto sin estructura reconocible\nlinea dos");

        $this->assertSame(
            'Se alcanzó el límite de peticiones del motor de IA. Inténtalo de nuevo en unos segundos.',
            $result['parse_warning']
        );
        Http::assertSentCount(3);
    }

    public function test_warns_when_groq_is_not_configured(): void
    {
        config(['services.groq.api_key' => null]);
        app()->forgetInstance(GroqService::class);
        app()->forgetInstance(InvoiceParserService::class);
        Http::fake();

        $result = $this->parse("texto sin estructura reconocible\nlinea dos");

        $this->assertSame(
            'El motor de IA no está configurado. Verifica los datos del recibo manualmente.',
            $result['parse_warning']
        );
        Http::assertNothingSent();
    }

    public function test_regex_path_skips_groq_when_items_are_found(): void
    {
        Http::fake();

        $result = $this->parse(
            "Factura #1234\nFecha: 2026-01-15\nProveedor: Distribuidora ABC\nTotal: 139000"
        );

        $this->assertNull($result['parse_warning']);
        $this->assertSame('1234', $result['invoice_number']);
        $this->assertSame(139000.0, $result['total']);
        Http::assertNothingSent();
    }
}
