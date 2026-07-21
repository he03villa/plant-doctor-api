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
            new OA\Response(response: 200, description: 'Dashboard data'),
            new OA\Response(response: 404, description: 'Store not found'),
            new OA\Response(response: 500, description: 'Server error'),
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
