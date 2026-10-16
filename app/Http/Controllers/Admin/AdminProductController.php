<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreProductResource;
use App\Models\StoreProduct;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AdminProductController extends Controller
{
    use ApiResponseTrait;

    #[OA\Get(
        path: '/api/admin/products',
        summary: 'List all products across stores (admin)',
        tags: ['Admin - Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'store_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'active', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Products listed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 15), 50);
            $search = $request->query('search');
            $storeId = $request->query('store_id');
            $category = $request->query('category');
            $active = $request->query('active');

            $products = StoreProduct::with('store')
                ->when($search, function ($q, $s) {
                    $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                        ->orWhere('sku', 'like', "%{$s}%")
                        ->orWhere('barcode', 'like', "%{$s}%"));
                })
                ->when($storeId, fn ($q, $sid) => $q->where('store_id', $sid))
                ->when($category, fn ($q, $cat) => $q->where('category', $cat))
                ->when($active !== null, fn ($q) => $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN)))
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return StoreProductResource::collection($products);
        } catch (Exception $e) {
            return $this->errorResponse('Error listing products: '.$e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/products/{id}',
        summary: 'Get a product (admin)',
        tags: ['Admin - Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product retrieved'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $product = StoreProduct::with('store')->find($id);

            if (! $product) {
                return $this->notFoundResponse('Product not found');
            }

            return $this->successResponse(new StoreProductResource($product));
        } catch (Exception $e) {
            return $this->errorResponse('Error fetching product: '.$e->getMessage(), 500);
        }
    }

    #[OA\Patch(
        path: '/api/admin/products/{id}/toggle',
        summary: 'Toggle product active status',
        tags: ['Admin - Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product status updated'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function toggleVisibility(int $id): JsonResponse
    {
        try {
            $product = StoreProduct::find($id);

            if (! $product) {
                return $this->notFoundResponse('Product not found');
            }

            $product->is_active = ! $product->is_active;
            $product->save();

            return $this->successResponse(new StoreProductResource($product), 'Product status updated');
        } catch (Exception $e) {
            return $this->errorResponse('Error updating product: '.$e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/admin/products/{id}',
        summary: 'Delete a product (sale items keep the product name snapshot)',
        tags: ['Admin - Products'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product deleted'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $product = StoreProduct::find($id);

            if (! $product) {
                return $this->notFoundResponse('Product not found');
            }

            $product->delete();

            return $this->successResponse(null, 'Product deleted');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting product: '.$e->getMessage(), 500);
        }
    }
}
