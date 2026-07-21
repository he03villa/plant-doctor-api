<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getDashboard(Store $store, int $lowStockThreshold = 5): array
    {
        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
            'today' => $this->getTodayStats($store),
            'week' => $this->getWeekStats($store),
            'alerts' => $this->getAlerts($store, $lowStockThreshold),
            'top_products' => $this->getTopProducts($store),
        ];
    }

    private function getTodayStats(Store $store): array
    {
        $orders = Order::where('store_id', $store->id)
            ->whereDate('created_at', Carbon::today())
            ->first();

        $salesCount = Order::where('store_id', $store->id)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $salesTotal = Order::where('store_id', $store->id)
            ->whereDate('created_at', Carbon::today())
            ->sum('total');

        $itemsSold = OrderItem::whereHas('order', function ($query) use ($store) {
            $query->where('store_id', $store->id)
                ->whereDate('created_at', Carbon::today());
        })->sum('quantity');

        return [
            'sales_count' => (int) $salesCount,
            'sales_total' => (float) $salesTotal,
            'items_sold' => (int) $itemsSold,
        ];
    }

    private function getWeekStats(Store $store): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $weekData = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->selectRaw('COALESCE(SUM(total), 0) as sales_total, COALESCE(AVG(total), 0) as avg_ticket')
            ->first();

        $topDay = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->selectRaw('DATE(created_at) as day, SUM(total) as day_total')
            ->groupBy('day')
            ->orderByDesc('day_total')
            ->first();

        return [
            'sales_total' => (float) ($weekData->sales_total ?? 0),
            'avg_ticket' => (float) round($weekData->avg_ticket ?? 0, 2),
            'top_day' => $topDay?->day,
        ];
    }

    private function getAlerts(Store $store, int $lowStockThreshold): array
    {
        $lowStockCount = StoreProduct::where('store_id', $store->id)
            ->where('stock_quantity', '<=', $lowStockThreshold)
            ->where('is_active', true)
            ->count();

        $pendingInvoices = Order::where('store_id', $store->id)
            ->whereIn('status', ['pending', 'processed'])
            ->count();

        return [
            'low_stock_count' => (int) $lowStockCount,
            'pending_invoices' => (int) $pendingInvoices,
        ];
    }

    private function getTopProducts(Store $store): array
    {
        return OrderItem::whereHas('order', function ($query) use ($store) {
            $query->where('store_id', $store->id);
        })
            ->select(
                'product_name',
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('SUM(total_price) as revenue')
            )
            ->groupBy('product_name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'name' => $item->product_name,
                'total_sold' => (int) $item->total_sold,
                'revenue' => (float) $item->revenue,
            ])
            ->toArray();
    }
}
