<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AdminSaleController extends Controller
{
    use ApiResponseTrait;

    #[OA\Get(
        path: '/api/admin/sales',
        summary: 'List all sales across stores (admin)',
        tags: ['Admin - Sales'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'store_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'payment_method', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sales listed'),
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
            $status = $request->query('status');
            $paymentMethod = $request->query('payment_method');
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');

            $sales = Sale::with(['store', 'user', 'items.product', 'payments'])
                ->when($search, function ($q, $s) {
                    $q->where(fn ($w) => $w->where('invoice_number', 'like', "%{$s}%")
                        ->orWhereHas('store', fn ($sq) => $sq->where('name', 'like', "%{$s}%")));
                })
                ->when($storeId, fn ($q, $sid) => $q->where('store_id', $sid))
                ->when($status, fn ($q, $st) => $q->where('status', $st))
                ->when($paymentMethod, fn ($q, $pm) => $q->where('payment_method', $pm))
                ->when($dateFrom, fn ($q, $df) => $q->whereDate('created_at', '>=', $df))
                ->when($dateTo, fn ($q, $dt) => $q->whereDate('created_at', '<=', $dt))
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return SaleResource::collection($sales);
        } catch (Exception $e) {
            return $this->errorResponse('Error listing sales: '.$e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/sales/{id}',
        summary: 'Get a sale with items and payments (admin)',
        tags: ['Admin - Sales'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sale retrieved'),
            new OA\Response(response: 404, description: 'Sale not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $sale = Sale::with(['store', 'user', 'items.product', 'payments'])->find($id);

            if (! $sale) {
                return $this->notFoundResponse('Sale not found');
            }

            return $this->successResponse(new SaleResource($sale));
        } catch (Exception $e) {
            return $this->errorResponse('Error fetching sale: '.$e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/admin/sales/{id}',
        summary: 'Delete a sale (items and payments are removed by cascade)',
        tags: ['Admin - Sales'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sale deleted'),
            new OA\Response(response: 404, description: 'Sale not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $sale = Sale::find($id);

            if (! $sale) {
                return $this->notFoundResponse('Sale not found');
            }

            $sale->delete();

            return $this->successResponse(null, 'Sale deleted');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting sale: '.$e->getMessage(), 500);
        }
    }
}
