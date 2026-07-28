<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Store;

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
