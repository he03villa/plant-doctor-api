<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Traits\ApiResponseTrait;
use App\Traits\StoreScoped;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Exception;

class PaymentController extends Controller
{
    use ApiResponseTrait, StoreScoped;

    #[OA\Get(
        path: '/api/payments',
        summary: 'List payments for authenticated store',
        tags: ['Payments'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payments listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            new OA\Property(property: 'total', type: 'integer', example: 5),
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($request->user());

            $payments = Payment::where('store_id', $store->id)
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return $this->successResponse($payments);
        } catch (Exception $e) {
            return $this->errorResponse('Error listing payments: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/payments/recent',
        summary: 'Get last 5 payments for authenticated store',
        tags: ['Payments'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Recent payments',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
                    ]
                )
            ),
        ]
    )]
    public function recent(Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($request->user());

            $payments = Payment::where('store_id', $store->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return $this->successResponse($payments);
        } catch (Exception $e) {
            return $this->errorResponse('Error listing recent payments: ' . $e->getMessage(), 500);
        }
    }
}
