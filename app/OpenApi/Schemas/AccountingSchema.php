<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProfitLossResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Profit & loss retrieved successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'period', type: 'object', properties: [
                new OA\Property(property: 'month', type: 'integer', example: 7),
                new OA\Property(property: 'year', type: 'integer', example: 2026),
                new OA\Property(property: 'from', type: 'string', format: 'date-time'),
                new OA\Property(property: 'to', type: 'string', format: 'date-time'),
            ]),
            new OA\Property(property: 'income', type: 'object', properties: [
                new OA\Property(property: 'total', type: 'number', example: 5000000),
                new OA\Property(property: 'by_payment_method', type: 'array', items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'method', type: 'string', example: 'cash'),
                        new OA\Property(property: 'total', type: 'number', example: 2500000),
                    ]
                )),
            ]),
            new OA\Property(property: 'expenses', type: 'object', properties: [
                new OA\Property(property: 'total', type: 'number', example: 3200000),
                new OA\Property(property: 'by_type', type: 'array', items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'proveedor'),
                        new OA\Property(property: 'total', type: 'number', example: 2000000),
                        new OA\Property(property: 'count', type: 'integer', example: 5),
                    ]
                )),
            ]),
            new OA\Property(property: 'net_profit', type: 'number', example: 1800000),
            new OA\Property(property: 'margin_percent', type: 'number', example: 36.0),
            new OA\Property(property: 'previous_period', type: 'object', properties: [
                new OA\Property(property: 'income', type: 'number', example: 4500000),
                new OA\Property(property: 'expenses', type: 'number', example: 3000000),
                new OA\Property(property: 'net_profit', type: 'number', example: 1500000),
            ]),
        ]),
    ]
)]

#[OA\Schema(
    schema: 'DailySalesResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Daily sales retrieved successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'date', type: 'string', example: '2026-07-28'),
            new OA\Property(property: 'summary', type: 'object', properties: [
                new OA\Property(property: 'total_amount', type: 'number', example: 850000),
                new OA\Property(property: 'total_count', type: 'integer', example: 12),
            ]),
            new OA\Property(property: 'by_payment_method', type: 'array', items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'method', type: 'string', example: 'cash'),
                    new OA\Property(property: 'total', type: 'number', example: 500000),
                    new OA\Property(property: 'count', type: 'integer', example: 7),
                ]
            )),
            new OA\Property(property: 'sales', type: 'array', items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'invoice_number', type: 'string', example: '001-045'),
                    new OA\Property(property: 'total', type: 'number', example: 125000),
                    new OA\Property(property: 'payment_method', type: 'string', example: 'cash'),
                    new OA\Property(property: 'items_count', type: 'integer', example: 3),
                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                ]
            )),
        ]),
    ]
)]

#[OA\Schema(
    schema: 'TaxSummaryResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Tax summary retrieved successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'period', type: 'object', properties: [
                new OA\Property(property: 'month', type: 'integer', example: 7),
                new OA\Property(property: 'year', type: 'integer', example: 2026),
                new OA\Property(property: 'from', type: 'string', format: 'date-time'),
                new OA\Property(property: 'to', type: 'string', format: 'date-time'),
            ]),
            new OA\Property(property: 'iva_collected', type: 'number', example: 950000),
            new OA\Property(property: 'iva_paid', type: 'number', example: 608000),
            new OA\Property(property: 'iva_net', type: 'number', example: 342000),
            new OA\Property(property: 'previous_period', type: 'object', properties: [
                new OA\Property(property: 'iva_collected', type: 'number', example: 855000),
                new OA\Property(property: 'iva_paid', type: 'number', example: 570000),
                new OA\Property(property: 'iva_net', type: 'number', example: 285000),
            ]),
        ]),
    ]
)]

#[OA\Schema(
    schema: 'BalanceSheetResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Balance sheet retrieved successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'as_of', type: 'string', format: 'date-time'),
            new OA\Property(property: 'assets', type: 'object', properties: [
                new OA\Property(property: 'inventory_value', type: 'number', example: 15000000),
                new OA\Property(property: 'total_assets', type: 'number', example: 15000000),
            ]),
            new OA\Property(property: 'liabilities', type: 'object', properties: [
                new OA\Property(property: 'accounts_payable', type: 'number', example: 3200000),
                new OA\Property(property: 'total_liabilities', type: 'number', example: 3200000),
            ]),
            new OA\Property(property: 'equity', type: 'object', properties: [
                new OA\Property(property: 'retained_earnings', type: 'number', example: 11800000),
                new OA\Property(property: 'total_equity', type: 'number', example: 11800000),
            ]),
        ]),
    ]
)]

#[OA\Schema(
    schema: 'MonthlyCloseResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Monthly close retrieved successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'period', type: 'object', properties: [
                new OA\Property(property: 'month', type: 'integer', example: 7),
                new OA\Property(property: 'year', type: 'integer', example: 2026),
                new OA\Property(property: 'from', type: 'string', format: 'date-time'),
                new OA\Property(property: 'to', type: 'string', format: 'date-time'),
            ]),
            new OA\Property(property: 'net_result', type: 'number', example: 1800000),
        ]),
    ]
)]
class AccountingSchema
{
}
