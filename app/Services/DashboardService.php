<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use App\Models\SaleItem;
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
            'inventory' => $this->getInventorySummary($store),
            'payment_methods' => $this->getPaymentMethods($store, $range, $prevRange),
            'expenses' => $this->getExpensesSummary($store, $range, $prevRange),
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
        $salesCount = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', $range)
            ->count();

        $salesTotal = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', $range)
            ->sum('total');

        $prevSalesCount = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', $prevRange)
            ->count();

        $prevSalesTotal = Sale::where('store_id', $store->id)
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

        $dailySales = Sale::where('store_id', $store->id)
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
        return SaleItem::whereHas('sale', function ($query) use ($store, $range) {
            $query->where('store_id', $store->id)
                ->whereBetween('created_at', $range);
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

    private function getInventorySummary(Store $store): array
    {
        $total = StoreProduct::where('store_id', $store->id)->count();
        $active = StoreProduct::where('store_id', $store->id)->where('is_active', true)->count();
        $lowStock = StoreProduct::where('store_id', $store->id)
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->where('stock_quantity', '>', 0)
            ->count();
        $outOfStock = StoreProduct::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('stock_quantity', '<=', 0)
            ->count();

        $categories = StoreProduct::where('store_id', $store->id)
            ->where('is_active', true)
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'name' => $row->category ?? 'Sin categoría',
                'count' => (int) $row->count,
            ])
            ->toArray();

        return [
            'total_products' => $total,
            'active_products' => $active,
            'low_stock_count' => $lowStock,
            'out_of_stock_count' => $outOfStock,
            'categories' => $categories,
        ];
    }

    private function getPaymentMethods(Store $store, array $range, array $prevRange): array
    {
        $current = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', $range)
            ->select('payment_method', DB::raw('SUM(total) as total'), DB::raw('count(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $previous = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', $prevRange)
            ->select('payment_method', DB::raw('SUM(total) as total'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $labels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia'];

        return collect(['cash', 'card', 'transfer'])->map(fn($method) => [
            'method' => $method,
            'label' => $labels[$method],
            'total' => (float) ($current[$method]->total ?? 0),
            'count' => (int) ($current[$method]->count ?? 0),
        ])->toArray();
    }

    private function getExpensesSummary(Store $store, array $range, array $prevRange): array
    {
        $totalSpent = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $range)
            ->sum('total');

        $invoiceCount = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $range)
            ->count();

        $prevTotal = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $prevRange)
            ->sum('total');

        $prevCount = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $prevRange)
            ->count();

        $totalTrend = $this->calculateTrendPercent((float) $totalSpent, (float) $prevTotal);
        $countTrend = $this->calculateTrendPercent((float) $invoiceCount, (float) $prevCount);

        $byType = Order::where('store_id', $store->id)
            ->whereBetween('created_at', $range)
            ->select('type', DB::raw('SUM(total) as total'), DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->map(fn($row) => [
                'type' => $row->type,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->toArray();

        return [
            'total_spent' => (float) $totalSpent,
            'invoice_count' => (int) $invoiceCount,
            'total_trend' => $totalTrend['value'],
            'total_trend_dir' => $totalTrend['direction'],
            'count_trend' => $countTrend['value'],
            'count_trend_dir' => $countTrend['direction'],
            'by_type' => $byType,
        ];
    }
}
