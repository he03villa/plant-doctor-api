<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Payment',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'subscription_id', type: 'integer', example: 1),
        new OA\Property(property: 'store_id', type: 'integer', example: 1),
        new OA\Property(property: 'amount', type: 'number', example: 9.99),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', type: 'string', example: 'succeeded'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Plan Pro (monthly)'),
        new OA\Property(property: 'receipt_url', type: 'string', nullable: true),
        new OA\Property(property: 'transaction_id', type: 'string', nullable: true, example: 'manual_abc123'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class PaymentSchema
{
}
