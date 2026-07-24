<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StoreProduct;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class SaleController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/stores/{store}/sales
     */
    #[OA\Get(
        path: '/api/stores/{store}/sales',
        summary: 'List sales for a store',
        tags: ['Sales'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sales listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Sale')),
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            new OA\Property(property: 'total', type: 'integer', example: 10),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function index(Request $request, int $store): JsonResponse
    {
        try {
            $sales = Sale::forStore($store)
                ->with('items', 'user', 'store')
                ->latest()
                ->paginate(15);

            return $this->successResponse(SaleResource::collection($sales));
        } catch (Exception $e) {
            return $this->errorResponse('Error listing sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/stores/{store}/sales
     */
    #[OA\Post(
        path: '/api/stores/{store}/sales',
        summary: 'Create a new sale and deduct stock',
        tags: ['Sales'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreSaleRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Sale created',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Venta registrada exitosamente'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Sale'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function store(StoreSaleRequest $request, int $store): JsonResponse
    {
        try {
            $validated = $request->validated();

            $sale = DB::transaction(function () use ($validated, $store) {
                $subtotal = 0;
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $product = StoreProduct::findOrFail($item['product_id']);

                    $totalPrice = $item['quantity'] * $item['unit_price'];
                    $subtotal += $totalPrice;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $totalPrice,
                    ];

                    if ($product->stock_quantity < $item['quantity']) {
                        throw ValidationException::withMessages([
                            "items.{$item['product_id']}" => "Stock insuficiente para '{$product->name}'. Disponible: {$product->stock_quantity}",
                        ]);
                    }

                    $product->decrement('stock_quantity', $item['quantity']);
                }

                $tax = 0;
                $total = $subtotal + $tax;

                $sale = Sale::create([
                    'store_id' => $store,
                    'user_id' => Auth::id(),
                    'invoice_number' => 'V-' . strtoupper(Str::random(6)),
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'currency' => 'COP',
                    'payment_method' => $validated['payment_method'],
                    'status' => 'completed',
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($itemsData as $itemData) {
                    SaleItem::create(array_merge($itemData, ['sale_id' => $sale->id]));
                }

                return $sale->load('items', 'store', 'user');
            });

            return $this->successResponse(
                new SaleResource($sale),
                'Venta registrada exitosamente',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error al registrar la venta: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/stores/{store}/sales/{sale}
     */
    #[OA\Get(
        path: '/api/stores/{store}/sales/{sale}',
        summary: 'Get sale details',
        tags: ['Sales'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sale', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sale retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Sale'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Sale not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function show(int $store, int $id): JsonResponse
    {
        try {
            $sale = Sale::forStore($store)->with('items', 'store', 'user')->find($id);

            if (!$sale) {
                return $this->notFoundResponse('Venta no encontrada');
            }

            return $this->successResponse(new SaleResource($sale));
        } catch (Exception $e) {
            return $this->errorResponse('Error getting sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/stores/{store}/sales/{sale}
     */
    #[OA\Delete(
        path: '/api/stores/{store}/sales/{sale}',
        summary: 'Cancel a sale and restore stock',
        tags: ['Sales'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sale', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sale cancelled',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Sale not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function destroy(int $store, int $id): JsonResponse
    {
        try {
            $sale = Sale::forStore($store)->with('items')->find($id);

            if (!$sale) {
                return $this->notFoundResponse('Venta no encontrada');
            }

            DB::transaction(function () use ($sale) {
                foreach ($sale->items as $item) {
                    if ($item->product_id) {
                        StoreProduct::where('id', $item->product_id)
                            ->increment('stock_quantity', $item->quantity);
                    }
                }

                $sale->update(['status' => 'cancelled']);
            });

            return $this->successResponse(null, 'Venta cancelada');
        } catch (Exception $e) {
            return $this->errorResponse('Error al cancelar la venta: ' . $e->getMessage(), 500);
        }
    }
}
