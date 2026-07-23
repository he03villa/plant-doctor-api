<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreSubscriptionRequest',
    type: 'object',
    required: ['plan_id'],
    properties: [
        new OA\Property(property: 'plan_id', type: 'integer', example: 2, description: 'ID of the plan to subscribe to'),
        new OA\Property(property: 'billing_cycle', type: 'string', enum: ['monthly', 'yearly'], example: 'monthly', description: 'Billing cycle (default: monthly)'),
    ]
)]
class StoreSubscriptionRequest
{
}
