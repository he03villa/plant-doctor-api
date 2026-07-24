<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderPayment',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'amount', type: 'number', example: 50000),
        new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'card', 'transfer'], example: 'cash'),
        new OA\Property(property: 'payment_date', type: 'string', format: 'date', example: '2026-07-23'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Primer abono'),
        new OA\Property(property: 'receipt_image_url', type: 'string', nullable: true, example: 'https://res.cloudinary.com/xxx/image/upload/...'),
        new OA\Property(property: 'user', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Juan Perez'),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class OrderPaymentSchema
{
}
