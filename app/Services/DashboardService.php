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
    public function getDashboard(Store $store, string $period = 'today', int $lowStockThreshold = 5): array
    {
        $range = $this->getPeriodRange($period);
        $prevRange = $this->getPreviousPeriodRange($period);

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
            'period' => $period,
            'summary' => $this->getSummary($store, $range, $prevRange, $lowStockThreshold),
            'chart' => $this->getWeeklyChart($store),
            'top_products' => $this->getTopProducts($store, $range),
            'recent_invoices' => $this->getRecentInvoices($store),
        ];
    }

    private function getPeriodRange(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::tomorrow()],
            'week'  => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()->addDay()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()->addDay()],
            'year'  => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()->addDay()],
            default => [Carbon::today(), Carbon::tomorrow()],
        };
    }

    private function getPreviousPeriodRange(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::yesterday(), Carbon::today()],
            'week'  => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()->addDay()],
            'month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()->addDay()],
            'year'  => [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()->addDay()],
            default => [Carbon::yesterday(), Carbon::today()],
        };
    }

    private function getSummary(Store $store, array $range, array $prevRange, int $lowStockThreshold): array
    {
        $salesCount = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $range)
            ->count();

        $salesTotal = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $range)
            ->sum('total');

        $prevSalesCount = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $prevRange)
            ->count();

        $prevSalesTotal = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $prevRange)
            ->sum('total');

        $lowStockCount = StoreProduct::where('store_id', $store->id)
            ->where('stock_quantity', '<=', $lowStockThreshold)
            ->where('is_active', true)
            ->count();

        $salesTotalTrend = $this->calculateTrendPercent((float) $salesTotal, (float) $prevSalesTotal);
        $salesCountTrend = $this->calculateTrendPercent((float) $salesCount, (float) $prevSalesCount);

        return [
            'sales_total' => (float) $salesTotal,
            'sales_count' => (int) $salesCount,
            'low_stock_alerts' => (int) $lowStockCount,
            'sales_total_trend' => $salesTotalTrend['value'],
            'sales_total_trend_dir' => $salesTotalTrend['direction'],
            'sales_count_trend' => $salesCountTrend['value'],
            'sales_count_trend_dir' => $salesCountTrend['direction'],
            'low_stock_trend' => 0,
            'low_stock_trend_dir' => 'same',
        ];
    }

    private function calculateTrendPercent(float $current, float $previous): array
    {
        if ($previous == 0 && $current == 0) {
            return ['value' => 0, 'direction' => 'same'];
        }

        if ($previous == 0) {
            return ['value' => 100, 'direction' => 'up'];
        }

        $percent = (int) round((($current - $previous) / $previous) * 100);

        if ($percent > 0) {
            return ['value' => $percent, 'direction' => 'up'];
        }

        if ($percent < 0) {
            return ['value' => abs($percent), 'direction' => 'down'];
        }

        return ['value' => 0, 'direction' => 'same'];
    }

    private function getWeeklyChart(Store $store): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek()->addDay();

        $dailySales = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->selectRaw('EXTRACT(ISODOW FROM created_at) as day_num, SUM(total) as total')
            ->groupBy('day_num')
            ->get()
            ->mapWithKeys(fn($row) => [$this->dayNumToLabel((int)$row->day_num) => (float) $row->total])
            ->toArray();

        $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        $data = [];
        foreach ($labels as $label) {
            $data[] = (float) ($dailySales[$label] ?? 0);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    private function getTopProducts(Store $store, array $range): array
    {
        return OrderItem::whereHas('order', function ($query) use ($store, $range) {
            $query->where('store_id', $store->id)
                ->whereBetween('orders.created_at', $range);
        })
            ->select(
                'product_name',
                DB::raw('SUM(quantity) as quantity_sold')
            )
            ->groupBy('product_name')
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->get()
            ->map(fn($item, $index) => [
                'rank' => $index + 1,
                'name' => $item->product_name,
                'quantity_sold' => (int) $item->quantity_sold,
            ])
            ->toArray();
    }

    private function getRecentInvoices(Store $store): array
    {
        return Order::where('store_id', $store->id)
            ->whereNotNull('invoice_number')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn($order) => [
                'id' => $order->id,
                'invoice_number' => $order->invoice_number,
                'supplier_name' => $order->supplier_name,
                'total' => (float) $order->total,
                'status' => $order->status,
                'created_at' => $order->created_at->toISOString(),
            ])
            ->toArray();
    }

    private function dayNumToLabel(int $dayNum): string
    {
        return match ($dayNum) {
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
            7 => 'Sun',
            default => '',
        };
    }
}
