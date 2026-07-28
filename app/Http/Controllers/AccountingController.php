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

    #[OA\Get(
        path: '/api/vivero/accounting/daily-sales',
        summary: 'Daily sales report',
        tags: ['Vivero'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(
                name: 'date',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date', default: '2026-07-28'),
                description: 'Date (Y-m-d)'
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daily sales data',
                content: new OA\JsonContent(ref: '#/components/schemas/DailySalesResponse')
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function dailySales(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return $this->notFoundResponse('No store found for this user');
            }

            $date = $request->query('date', now()->format('Y-m-d'));

            $data = $this->accountingService->getDailySales($store, $date);

            return $this->successResponse($data, 'Daily sales retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/vivero/accounting/tax-summary',
        summary: 'Tax summary (IVA)',
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
            new OA\Response(response: 200, description: 'Tax summary data',
                content: new OA\JsonContent(ref: '#/components/schemas/TaxSummaryResponse')
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function taxSummary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return $this->notFoundResponse('No store found for this user');
            }

            $month = (int) $request->query('month', now()->month);
            $year = (int) $request->query('year', now()->year);

            $data = $this->accountingService->getTaxSummary($store, $month, $year);

            return $this->successResponse($data, 'Tax summary retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/vivero/accounting/balance-sheet',
        summary: 'Balance sheet',
        tags: ['Vivero'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Balance sheet data',
                content: new OA\JsonContent(ref: '#/components/schemas/BalanceSheetResponse')
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function balanceSheet(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return $this->notFoundResponse('No store found for this user');
            }

            $data = $this->accountingService->getBalanceSheet($store);

            return $this->successResponse($data, 'Balance sheet retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/vivero/accounting/monthly-close',
        summary: 'Monthly close report',
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
            new OA\Response(response: 200, description: 'Monthly close data',
                content: new OA\JsonContent(ref: '#/components/schemas/MonthlyCloseResponse')
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function monthlyClose(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $store = Store::where('user_id', $user->id)->first();

            if (!$store) {
                return $this->notFoundResponse('No store found for this user');
            }

            $month = (int) $request->query('month', now()->month);
            $year = (int) $request->query('year', now()->year);

            $data = $this->accountingService->getMonthlyClose($store, $month, $year);

            return $this->successResponse($data, 'Monthly close retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
}
