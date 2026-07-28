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
class AccountingSchema
{
}
