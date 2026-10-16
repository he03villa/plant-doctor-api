<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AdminStatsController extends Controller
{
    use ApiResponseTrait;

    #[OA\Get(
        path: '/api/admin/stats',
        summary: 'Global platform stats (admin)',
        tags: ['Admin - Stats'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stats retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'total_stores', type: 'integer'),
                            new OA\Property(property: 'active_stores', type: 'integer'),
                            new OA\Property(property: 'total_users', type: 'integer'),
                            new OA\Property(property: 'total_products', type: 'integer'),
                            new OA\Property(property: 'total_sales', type: 'integer'),
                            new OA\Property(property: 'active_subscriptions', type: 'integer'),
                            new OA\Property(property: 'revenue_total', type: 'number', format: 'float'),
                            new OA\Property(property: 'revenue_month', type: 'number', format: 'float'),
                            new OA\Property(property: 'plan_distribution', type: 'array', items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'plan', type: 'string'),
                                    new OA\Property(property: 'count', type: 'integer'),
                                ]
                            )),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(): JsonResponse
    {
        try {
            $totalStores = Store::count();
            $activeStores = Store::where('is_active', true)->count();
            $totalUsers = User::count();
            $totalProducts = StoreProduct::count();
            $totalSales = Sale::count();
            $activeSubscriptions = Subscription::whereIn('status', ['active', 'trialing'])->count();

            $revenueTotal = Payment::where('status', 'succeeded')->sum('amount');
            $revenueMonth = Payment::where('status', 'succeeded')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');

            $planDistribution = Subscription::join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->groupBy('plans.slug')
                ->selectRaw('plans.slug as plan, count(*) as count')
                ->get();

            $distribution = [];
            foreach ($planDistribution as $row) {
                $distribution[] = ['plan' => $row->plan, 'count' => (int) $row->count];
            }

            return $this->successResponse([
                'total_stores' => $totalStores,
                'active_stores' => $activeStores,
                'total_users' => $totalUsers,
                'total_products' => $totalProducts,
                'total_sales' => $totalSales,
                'active_subscriptions' => $activeSubscriptions,
                'revenue_total' => (float) $revenueTotal,
                'revenue_month' => (float) $revenueMonth,
                'plan_distribution' => $distribution,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Error fetching stats: '.$e->getMessage(), 500);
        }
    }
}
