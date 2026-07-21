<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStoreProductRequest;
use App\Http\Requests\UpdateStoreProductRequest;
use App\Http\Resources\StoreProductResource;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class ProductController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/stores/{store}/products
     * List products for a store
     */
    #[OA\Get(
        path: '/api/stores/{store}/products',
        summary: 'List store products',
        tags: ['Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'visible_only', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Products listed'),
            new OA\Response(response: 404, description: 'Store not found'),
        ]
    )]
    public function index(Request $request, int $storeId): AnonymousResourceCollection
    {
        $store = Store::find($storeId);

        if (!$store) {
            return $this->notFoundResponse('Store not found');
        }

        $query = $store->storeProducts();

        if ($request->query('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->boolean('visible_only')) {
            $query->visibleOnMap();
        }

        $products = $query->latest()->paginate(15);

        return StoreProductResource::collection($products);
    }

    /**
     * POST /api/stores/{store}/products
     * Create a new product
     */
    #[OA\Post(
        path: '/api/stores/{store}/products',
        summary: 'Create a store product',
        tags: ['Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateStoreProductRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created'),
            new OA\Response(response: 404, description: 'Store not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(CreateStoreProductRequest $request, int $storeId): JsonResponse
    {
        try {
            $store = Store::find($storeId);

            if (!$store) {
                return $this->notFoundResponse('Store not found');
            }

            $product = $store->storeProducts()->create($request->validated());

            return $this->successResponse(
                new StoreProductResource($product),
                'Product created',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/stores/{store}/products/{product}
     * Show product details
     */
    #[OA\Get(
        path: '/api/stores/{store}/products/{product}',
        summary: 'Get product details',
        tags: ['Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product retrieved'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function show(int $storeId, int $id): JsonResponse
    {
        try {
            $product = StoreProduct::where('store_id', $storeId)->find($id);

            if (!$product) {
                return $this->notFoundResponse('Product not found');
            }

            return $this->successResponse(new StoreProductResource($product));
        } catch (Exception $e) {
            return $this->errorResponse('Error getting product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/stores/{store}/products/{product}
     * Update a product
     */
    #[OA\Put(
        path: '/api/stores/{store}/products/{product}',
        summary: 'Update a store product',
        tags: ['Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateStoreProductRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Product updated'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateStoreProductRequest $request, int $storeId, int $id): JsonResponse
    {
        try {
            $product = StoreProduct::where('store_id', $storeId)->find($id);

            if (!$product) {
                return $this->notFoundResponse('Product not found');
            }

            $product->update($request->validated());

            return $this->successResponse(new StoreProductResource($product), 'Product updated');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/stores/{store}/products/{product}
     * Delete a product
     */
    #[OA\Delete(
        path: '/api/stores/{store}/products/{product}',
        summary: 'Delete a store product',
        tags: ['Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product deleted'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function destroy(int $storeId, int $id): JsonResponse
    {
        try {
            $product = StoreProduct::where('store_id', $storeId)->find($id);

            if (!$product) {
                return $this->notFoundResponse('Product not found');
            }

            $product->delete();

            return $this->successResponse(null, 'Product deleted');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/stores/{store}/products/{product}/visibility
     * Toggle product visibility on map
     */
    #[OA\Patch(
        path: '/api/stores/{store}/products/{product}/visibility',
        summary: 'Toggle product map visibility',
        tags: ['Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Visibility toggled'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function toggleVisibility(int $storeId, int $id): JsonResponse
    {
        try {
            $product = StoreProduct::where('store_id', $storeId)->find($id);

            if (!$product) {
                return $this->notFoundResponse('Product not found');
            }

            $product->update(['is_visible_on_map' => !$product->is_visible_on_map]);

            return $this->successResponse(new StoreProductResource($product), 'Visibility toggled');
        } catch (Exception $e) {
            return $this->errorResponse('Error toggling visibility: ' . $e->getMessage(), 500);
        }
    }
}
