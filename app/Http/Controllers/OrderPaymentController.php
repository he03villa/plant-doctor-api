<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderPaymentRequest;
use App\Http\Resources\OrderPaymentResource;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Exception;

class OrderPaymentController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/orders/{order}/payments
     * List payments for an order
     */
    #[OA\Get(
        path: '/api/orders/{order}/payments',
        summary: 'List payments for an order',
        tags: ['OrderPayments'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payments listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderPayment')),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Order not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function index(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = Order::with('payments.user')->findOrFail($orderId);

            return $this->successResponse(
                OrderPaymentResource::collection($order->payments)
            );
        } catch (Exception $e) {
            return $this->errorResponse('Error listing payments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/orders/{order}/payments
     * Register a payment for an order
     */
    #[OA\Post(
        path: '/api/orders/{order}/payments',
        summary: 'Register a payment for an order',
        tags: ['OrderPayments'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreOrderPaymentRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Payment registered',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Pago registrado exitosamente'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/OrderPayment'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Order not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Validation error / amount exceeds balance',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function store(StoreOrderPaymentRequest $request, int $orderId): JsonResponse
    {
        try {
            $order = Order::with('payments')->findOrFail($orderId);

            $totalPaid = $order->payments->sum('amount');
            $remaining = $order->total - $totalPaid;

            if ($request->amount > $remaining + 0.01) {
                return $this->validationErrorResponse([
                    'amount' => ["El monto ({$request->amount}) excede el saldo pendiente ({$remaining})"],
                ]);
            }

            $payment = $order->payments()->create([
                'user_id' => Auth::id(),
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'notes' => $request->notes,
            ]);

            $payment->load('user');

            return $this->successResponse(
                new OrderPaymentResource($payment),
                'Pago registrado exitosamente',
                201
            );
        } catch (Exception $e) {
            return $this->errorResponse('Error creating payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/orders/{order}/payments/{payment}
     * Delete a payment
     */
    #[OA\Delete(
        path: '/api/orders/{order}/payments/{payment}',
        summary: 'Delete a payment',
        tags: ['OrderPayments'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment deleted',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Payment not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function destroy(int $orderId, int $paymentId): JsonResponse
    {
        try {
            $payment = OrderPayment::where('order_id', $orderId)->findOrFail($paymentId);
            $payment->delete();

            return $this->successResponse(null, 'Pago eliminado exitosamente');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting payment: ' . $e->getMessage(), 500);
        }
    }
}
