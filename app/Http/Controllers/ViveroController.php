<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Traits\ApiResponseTrait;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class ViveroController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * GET /api/vivero/dashboard
     * Store owner dashboard with sales stats, alerts, and top products
     */
    #[OA\Get(
        path: '/api/vivero/dashboard',
        summary: 'Store owner dashboard',
        tags: ['Vivero'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(
                name: 'low_stock_threshold',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 5),
                description: 'Threshold for low stock alerts'
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard data',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Dashboard retrieved successfully'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'store', type: 'object', properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Vivero Verde'),
                            ]),
                            new OA\Property(property: 'today', type: 'object', properties: [
                                new OA\Property(property: 'sales_count', type: 'integer', example: 3),
                                new OA\Property(property: 'sales_total', type: 'number', example: 275000),
                                new OA\Property(property: 'items_sold', type: 'integer', example: 12),
                            ]),
                            new OA\Property(property: 'week', type: 'object', properties: [
                                new OA\Property(property: 'sales_total', type: 'number', example: 1850000),
                                new OA\Property(property: 'avg_ticket', type: 'number', example: 185000),
                                new OA\Property(property: 'top_day', type: 'string', nullable: true, example: '2026-07-18'),
                            ]),
                            new OA\Property(property: 'alerts', type: 'object', properties: [
                                new OA\Property(property: 'low_stock_count', type: 'integer', example: 3),
                                new OA\Property(property: 'pending_invoices', type: 'integer', example: 2),
                            ]),
                            new OA\Property(property: 'top_products', type: 'array', items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'Fertilizante NPK'),
                                    new OA\Property(property: 'total_sold', type: 'integer', example: 45),
                                    new OA\Property(property: 'revenue', type: 'number', example: 1125000),
                                ]
                            )),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return $this->notFoundResponse('No store found for this user');
            }

            $lowStockThreshold = (int) $request->query('low_stock_threshold', 5);

            $data = $this->dashboardService->getDashboard($store, $lowStockThreshold);

            return $this->successResponse($data, 'Dashboard retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error getting dashboard: ' . $e->getMessage(), 500);
        }
    }
}
