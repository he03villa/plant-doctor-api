<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DashboardResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Dashboard retrieved successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'store', type: 'object', properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'Vivero El Sol'),
            ]),
            new OA\Property(property: 'period', type: 'string', example: 'today'),
            new OA\Property(property: 'summary', type: 'object', properties: [
                new OA\Property(property: 'sales_total', type: 'number', example: 1250000),
                new OA\Property(property: 'sales_count', type: 'integer', example: 23),
                new OA\Property(property: 'low_stock_alerts', type: 'integer', example: 5),
                new OA\Property(property: 'sales_total_trend', type: 'integer', example: 12, description: 'Percentage change vs previous period'),
                new OA\Property(property: 'sales_total_trend_dir', type: 'string', enum: ['up', 'down', 'same'], example: 'up'),
                new OA\Property(property: 'sales_count_trend', type: 'integer', example: 8, description: 'Percentage change vs previous period'),
                new OA\Property(property: 'sales_count_trend_dir', type: 'string', enum: ['up', 'down', 'same'], example: 'up'),
                new OA\Property(property: 'low_stock_trend', type: 'integer', example: 2, description: 'Absolute change vs previous period'),
                new OA\Property(property: 'low_stock_trend_dir', type: 'string', enum: ['up', 'down', 'same'], example: 'down'),
            ]),
            new OA\Property(property: 'chart', type: 'object', description: 'Last 7 days sales chart', properties: [
                new OA\Property(property: 'labels', type: 'array', items: new OA\Items(type: 'string', example: 'Mon'), example: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'number'), example: [120000, 95000, 180000, 210000, 175000, 300000, 170000]),
            ]),
            new OA\Property(property: 'top_products', type: 'array', items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'rank', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Ficus benjamina'),
                    new OA\Property(property: 'quantity_sold', type: 'integer', example: 45),
                ]
            )),
            new OA\Property(property: 'recent_invoices', type: 'array', items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'invoice_number', type: 'string', example: '4521'),
                    new OA\Property(property: 'supplier_name', type: 'string', example: 'Proveedor Verde'),
                    new OA\Property(property: 'total', type: 'number', example: 890000),
                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                ]
            )),
        ]),
    ]
)]
class DashboardSchema
{
}
