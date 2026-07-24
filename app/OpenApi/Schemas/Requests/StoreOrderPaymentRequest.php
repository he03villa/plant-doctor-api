<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreOrderPaymentRequest',
    type: 'object',
    required: ['amount', 'payment_method', 'payment_date'],
    properties: [
        new OA\Property(property: 'amount', type: 'number', minimum: 0.01, example: 50000),
        new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'card', 'transfer'], example: 'cash'),
        new OA\Property(property: 'payment_date', type: 'string', format: 'date', example: '2026-07-23'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Primer abono'),
    ]
)]
class StoreOrderPaymentRequest
{
}
