<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParseInvoiceRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Store;
use App\Services\OrderService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * POST /api/orders/parse
     * Parse OCR text from frontend Tesseract.js
     */
    #[OA\Post(
        path: '/api/orders/parse',
        summary: 'Parse OCR text from invoice',
        tags: ['Orders'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['ocr_text'],
                properties: [
                    new OA\Property(property: 'ocr_text', type: 'string', example: 'FACTURA #1234\nFecha: 2024-01-15\n...'),
                    new OA\Property(property: 'image', type: 'string', format: 'binary', description: 'Optional invoice image'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Invoice parsed'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function parse(ParseInvoiceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $image = $request->file('image');
            $result = $this->orderService->parseInvoice(
                $validated['ocr_text'],
                $image
            );

            return $this->successResponse($result, 'Invoice parsed successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error parsing invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/orders
     * Create a new order
     */
    #[OA\Post(
        path: '/api/orders',
        summary: 'Create a new order',
        tags: ['Orders'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreOrderRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order created'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $store = $this->getStoreForUser($user);

            $order = $this->orderService->create($user, $store, $request->validated());

            return $this->successResponse(
                new OrderResource($order),
                'Order created',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/orders
     * List orders for the authenticated user's store
     */
    #[OA\Get(
        path: '/api/orders',
        summary: 'List user orders',
        tags: ['Orders'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Orders listed'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $storeId = $request->query('store_id');
        $orders = $this->orderService->getUserOrders($request->user(), $storeId);

        return OrderResource::collection($orders);
    }

    /**
     * GET /api/orders/{order}
     * Get order details
     */
    #[OA\Get(
        path: '/api/orders/{order}',
        summary: 'Get order details',
        tags: ['Orders'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order retrieved'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($request->user(), $id);

            if (!$order) {
                return $this->notFoundResponse('Order not found');
            }

            return $this->successResponse(new OrderResource($order));
        } catch (Exception $e) {
            return $this->errorResponse('Error getting order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/orders/{order}
     * Update an order
     */
    #[OA\Put(
        path: '/api/orders/{order}',
        summary: 'Update an order',
        tags: ['Orders'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreOrderRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Order updated'),
            new OA\Response(response: 404, description: 'Order not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(StoreOrderRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($request->user(), $id);

            if (!$order) {
                return $this->notFoundResponse('Order not found');
            }

            $order = $this->orderService->update($order, $request->validated());

            return $this->successResponse(new OrderResource($order), 'Order updated');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/orders/{order}
     * Delete an order
     */
    #[OA\Delete(
        path: '/api/orders/{order}',
        summary: 'Delete an order',
        tags: ['Orders'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order deleted'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($request->user(), $id);

            if (!$order) {
                return $this->notFoundResponse('Order not found');
            }

            $this->orderService->delete($order);

            return $this->successResponse(null, 'Order deleted');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/orders/{order}/verify
     * Mark order as verified
     */
    #[OA\Post(
        path: '/api/orders/{order}/verify',
        summary: 'Verify an order',
        tags: ['Orders'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order verified'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function verify(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($request->user(), $id);

            if (!$order) {
                return $this->notFoundResponse('Order not found');
            }

            $order = $this->orderService->verify($order);

            return $this->successResponse(new OrderResource($order), 'Order verified');
        } catch (Exception $e) {
            return $this->errorResponse('Error verifying order: ' . $e->getMessage(), 500);
        }
    }

    private function getStoreForUser($user): Store
    {
        $store = Store::where('user_id', $user->id)->first();

        if (!$store) {
            throw new Exception('No store found for this user. Create a store first.');
        }

        return $store;
    }
}
