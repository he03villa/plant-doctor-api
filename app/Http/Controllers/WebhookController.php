<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private StripeService $stripeService
    ) {}

    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (! $sigHeader) {
            return $this->errorResponse('Firma de Stripe no proporcionada.', 400);
        }

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $sigHeader);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Firma de Stripe inválida.', 400);
        } catch (Exception $e) {
            Log::error('Stripe webhook error', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error procesando webhook: '.$e->getMessage(), 400);
        }

        try {
            $this->stripeService->handleWebhookEvent($event);
        } catch (Exception $e) {
            Log::error('Stripe webhook handler error', [
                'event_type' => $event->type,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['received' => true]);
    }
}
