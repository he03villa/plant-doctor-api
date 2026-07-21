<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Order',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'invoice_number', type: 'string', example: '1234'),
        new OA\Property(property: 'invoice_date', type: 'string', format: 'date-time', example: '2024-01-15'),
        new OA\Property(property: 'supplier_name', type: 'string', example: 'Distribuidora ABC'),
        new OA\Property(property: 'subtotal', type: 'number', example: 120000),
        new OA\Property(property: 'tax', type: 'number', example: 19000),
        new OA\Property(property: 'total', type: 'number', example: 139000),
        new OA\Property(property: 'currency', type: 'string', example: 'COP'),
        new OA\Property(property: 'invoice_image_url', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processed', 'verified', 'error']),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'ocr_confidence', type: 'number', nullable: true),
        new OA\Property(property: 'store', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItem')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class OrderSchema
{
}
