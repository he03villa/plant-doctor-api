<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

class AdminPaymentController extends Controller
{
    use ApiResponseTrait;

    #[OA\Get(
        path: '/api/admin/payments',
        summary: 'List all subscription payments (admin)',
        tags: ['Admin - Payments'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'store_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payments listed'),
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
            $status = $request->query('status');
            $storeId = $request->query('store_id');
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');

            $payments = Payment::with(['subscription.plan', 'store'])
                ->when($search, function ($q, $s) {
                    $q->where(fn ($w) => $w->where('transaction_id', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%")
                        ->orWhereHas('store', fn ($sq) => $sq->where('name', 'like', "%{$s}%")));
                })
                ->when($status, fn ($q, $st) => $q->where('status', $st))
                ->when($storeId, fn ($q, $sid) => $q->where('store_id', $sid))
                ->when($dateFrom, fn ($q, $df) => $q->whereDate('created_at', '>=', $df))
                ->when($dateTo, fn ($q, $dt) => $q->whereDate('created_at', '<=', $dt))
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return JsonResource::collection($payments);
        } catch (Exception $e) {
            return $this->errorResponse('Error listing payments: '.$e->getMessage(), 500);
        }
    }
}
