<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreProduct;

class AccountingService
{
    public function getProfitLoss(Store $store, int $month, int $year): array
    {
        $startDate = now()->createFromDate($year, $month, 1)->startOfDay();
        $endDate = (clone $startDate)->copy()->endOfMonth()->endOfDay();

        $prevStartDate = (clone $startDate)->copy()->subMonth()->startOfDay();
        $prevEndDate = (clone $startDate)->copy()->subDay()->endOfDay();

        $incomeTotal = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $incomeByMethod = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('payment_method, SUM(total) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(fn($row) => [
                'method' => $row->payment_method,
                'total' => (float) $row->total,
            ])
            ->values()
            ->toArray();

        $expensesTotal = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $expensesByType = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('type, SUM(total) as total, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->map(fn($row) => [
                'type' => $row->type,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->values()
            ->toArray();

        $prevIncome = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->sum('total');

        $prevExpenses = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->sum('total');

        $netProfit = $incomeTotal - $expensesTotal;
        $prevNetProfit = $prevIncome - $prevExpenses;
        $margin = $incomeTotal > 0 ? round(($netProfit / $incomeTotal) * 100, 1) : 0;

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'from' => $startDate->toISOString(),
                'to' => $endDate->toISOString(),
            ],
            'income' => [
                'total' => (float) $incomeTotal,
                'by_payment_method' => $incomeByMethod,
            ],
            'expenses' => [
                'total' => (float) $expensesTotal,
                'by_type' => $expensesByType,
            ],
            'net_profit' => (float) $netProfit,
            'margin_percent' => $margin,
            'previous_period' => [
                'income' => (float) $prevIncome,
                'expenses' => (float) $prevExpenses,
                'net_profit' => (float) $prevNetProfit,
            ],
        ];
    }

    public function exportCsv(Store $store, int $month, int $year): string
    {
        $data = $this->getProfitLoss($store, $month, $year);

        $lines = [];
        $lines[] = 'Estado de Resultados - ' . $this->monthName($month) . " $year";
        $lines[] = '';
        $lines[] = 'Ingresos,' . number_format($data['income']['total'], 0, ',', '.');

        foreach ($data['income']['by_payment_method'] as $method) {
            $label = match ($method['method']) {
                'cash' => 'Efectivo',
                'card' => 'Tarjeta',
                'transfer' => 'Transferencia',
                default => $method['method'],
            };
            $lines[] = "  $label," . number_format($method['total'], 0, ',', '.');
        }

        $lines[] = 'Gastos,' . number_format($data['expenses']['total'], 0, ',', '.');

        foreach ($data['expenses']['by_type'] as $type) {
            $label = match ($type['type']) {
                'proveedor' => 'Proveedores',
                'servicio' => 'Servicios',
                'otro' => 'Otros',
                default => $type['type'],
            };
            $lines[] = "  $label ({$type['count']} facturas)," . number_format($type['total'], 0, ',', '.');
        }

        $lines[] = '';
        $lines[] = 'Utilidad Neta,' . number_format($data['net_profit'], 0, ',', '.');
        $lines[] = 'Margen,' . $data['margin_percent'] . '%';
        $lines[] = '';
        $lines[] = 'Periodo anterior';
        $lines[] = 'Ingresos anteriores,' . number_format($data['previous_period']['income'], 0, ',', '.');
        $lines[] = 'Gastos anteriores,' . number_format($data['previous_period']['expenses'], 0, ',', '.');
        $lines[] = 'Utilidad anterior,' . number_format($data['previous_period']['net_profit'], 0, ',', '.');

        return implode("\r\n", $lines);
    }

    public function getDailySales(Store $store, string $date): array
    {
        $startDate = now()->createFromFormat('Y-m-d', $date)->startOfDay();
        $endDate = (clone $startDate)->copy()->endOfDay();

        $sales = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('items')
            ->orderByDesc('created_at')
            ->get();

        $totalAmount = $sales->sum('total');
        $totalCount = $sales->count();

        $byPaymentMethod = $sales->groupBy('payment_method')
            ->map(fn($group) => [
                'method' => $group->first()->payment_method,
                'total' => (float) $group->sum('total'),
                'count' => $group->count(),
            ])
            ->values()
            ->toArray();

        $salesList = $sales->map(fn($sale) => [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'total' => (float) $sale->total,
            'payment_method' => $sale->payment_method,
            'items_count' => $sale->items->count(),
            'created_at' => $sale->created_at->toISOString(),
        ])->toArray();

        return [
            'date' => $date,
            'summary' => [
                'total_amount' => (float) $totalAmount,
                'total_count' => $totalCount,
            ],
            'by_payment_method' => $byPaymentMethod,
            'sales' => $salesList,
        ];
    }

    public function getTaxSummary(Store $store, int $month, int $year): array
    {
        $startDate = now()->createFromDate($year, $month, 1)->startOfDay();
        $endDate = (clone $startDate)->copy()->endOfMonth()->endOfDay();

        $prevStartDate = (clone $startDate)->copy()->subMonth()->startOfDay();
        $prevEndDate = (clone $startDate)->copy()->subDay()->endOfDay();

        $ivaCollected = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('tax');

        $ivaPaid = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('tax');

        $prevIvaCollected = Sale::where('store_id', $store->id)
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->sum('tax');

        $prevIvaPaid = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->sum('tax');

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'from' => $startDate->toISOString(),
                'to' => $endDate->toISOString(),
            ],
            'iva_collected' => (float) $ivaCollected,
            'iva_paid' => (float) $ivaPaid,
            'iva_net' => (float) ($ivaCollected - $ivaPaid),
            'previous_period' => [
                'iva_collected' => (float) $prevIvaCollected,
                'iva_paid' => (float) $prevIvaPaid,
                'iva_net' => (float) ($prevIvaCollected - $prevIvaPaid),
            ],
        ];
    }

    public function getBalanceSheet(Store $store): array
    {
        $inventoryValue = StoreProduct::where('store_id', $store->id)
            ->where('is_active', true)
            ->get()
            ->sum(fn($p) => (float) $p->stock_quantity * (float) $p->purchase_price);

        $orders = Order::where('store_id', $store->id)
            ->whereIn('status', ['pending', 'partial'])
            ->with('payments')
            ->get();

        $outstandingPayables = $orders->sum(function ($order) {
            $paid = $order->payments->sum('amount');
            return max(0, (float) $order->total - (float) $paid);
        });

        $equity = $inventoryValue - $outstandingPayables;

        return [
            'as_of' => now()->toISOString(),
            'assets' => [
                'inventory_value' => round($inventoryValue, 2),
                'total_assets' => round($inventoryValue, 2),
            ],
            'liabilities' => [
                'accounts_payable' => round($outstandingPayables, 2),
                'total_liabilities' => round($outstandingPayables, 2),
            ],
            'equity' => [
                'retained_earnings' => round($equity, 2),
                'total_equity' => round($equity, 2),
            ],
        ];
    }

    public function getMonthlyClose(Store $store, int $month, int $year): array
    {
        $profitLoss = $this->getProfitLoss($store, $month, $year);
        $taxSummary = $this->getTaxSummary($store, $month, $year);

        return [
            'period' => $profitLoss['period'],
            'profit_loss' => $profitLoss,
            'tax_summary' => $taxSummary,
            'net_result' => $profitLoss['net_profit'],
        ];
    }

    private function monthName(int $month): string
    {
        $names = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        return $names[$month] ?? '';
    }
}
