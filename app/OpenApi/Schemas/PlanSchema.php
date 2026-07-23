<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Plan',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Pro'),
        new OA\Property(property: 'slug', type: 'string', example: 'pro'),
        new OA\Property(property: 'price_monthly', type: 'number', example: 9.99),
        new OA\Property(property: 'price_yearly', type: 'number', nullable: true, example: 99.99),
        new OA\Property(property: 'features', type: 'object', properties: [
            new OA\Property(property: 'max_products', type: 'integer', example: -1),
            new OA\Property(property: 'max_users', type: 'integer', example: 5),
            new OA\Property(property: 'has_map', type: 'boolean', example: true),
            new OA\Property(property: 'has_invoicing', type: 'boolean', example: true),
            new OA\Property(property: 'has_advanced_dashboard', type: 'boolean', example: true),
            new OA\Property(property: 'has_multi_store', type: 'boolean', example: false),
        ]),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'display_order', type: 'integer', example: 2),
    ]
)]
class PlanSchema
{
}
