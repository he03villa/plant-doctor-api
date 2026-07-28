<?php

namespace App\Http\Controllers;

use App\Services\AccountingService;
use App\Traits\ApiResponseTrait;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Exception;

class AccountingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AccountingService $accountingService
    ) {}

    #[OA\Get(
        path: '/api/vivero/accounting/profit-loss',
        summary: 'Profit & loss statement',
        tags: ['Vivero'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(
                name: 'month',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 12, default: 7),
                description: 'Month (1-12)'
            ),
            new OA\Parameter(
                name: 'year',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 2000, maximum: 2100, default: 2026),
                description: 'Year'
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Profit & loss data',
                content: new OA\JsonContent(ref: '#/components/schemas/ProfitLossResponse')
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function profitLoss(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return $this->notFoundResponse('No store found for this user');
            }

            $month = (int) $request->query('month', now()->month);
            $year = (int) $request->query('year', now()->year);

            $data = $this->accountingService->getProfitLoss($store, $month, $year);

            return $this->successResponse($data, 'Profit & loss retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/vivero/accounting/profit-loss/export',
        summary: 'Export profit & loss as CSV',
        tags: ['Vivero'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(
                name: 'month',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 12, default: 7),
                description: 'Month (1-12)'
            ),
            new OA\Parameter(
                name: 'year',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 2000, maximum: 2100, default: 2026),
                description: 'Year'
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'CSV file download',
                content: new OA\MediaType(mediaType: 'text/csv')
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function export(Request $request)
    {
        try {
            $user = $request->user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return $this->notFoundResponse('No store found for this user');
            }

            $month = (int) $request->query('month', now()->month);
            $year = (int) $request->query('year', now()->year);

            $csv = $this->accountingService->exportCsv($store, $month, $year);

            $filename = "estado-resultados-{$month}-{$year}.csv";

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Content-Length' => strlen($csv),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Error exporting report: ' . $e->getMessage(), 500);
        }
    }
}
