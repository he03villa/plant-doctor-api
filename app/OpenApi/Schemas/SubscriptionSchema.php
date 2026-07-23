<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Subscription',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'store_id', type: 'integer', example: 1),
        new OA\Property(property: 'plan', ref: '#/components/schemas/Plan'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'trial_ends_at', type: 'string', nullable: true, format: 'date-time'),
        new OA\Property(property: 'current_period_start', type: 'string', format: 'date-time', example: '2026-07-01T00:00:00.000000Z'),
        new OA\Property(property: 'current_period_end', type: 'string', format: 'date-time', example: '2026-08-01T00:00:00.000000Z'),
        new OA\Property(property: 'cancelled_at', type: 'string', nullable: true, format: 'date-time'),
        new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'card'),
        new OA\Property(property: 'last_payment_at', type: 'string', nullable: true, format: 'date-time'),
        new OA\Property(property: 'last_payment_amount', type: 'number', nullable: true, example: 9.99),
        new OA\Property(property: 'days_until_renewal', type: 'integer', example: 11),
        new OA\Property(property: 'is_trial', type: 'boolean', example: false),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
class SubscriptionSchema
{
}
