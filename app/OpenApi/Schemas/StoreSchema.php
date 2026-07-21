<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Store',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Vivero Verde'),
        new OA\Property(property: 'address', type: 'string', example: 'Calle 123 #45-67'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+57 300 1234567'),
        new OA\Property(property: 'latitude', type: 'number', format: 'double', example: 4.7110),
        new OA\Property(property: 'longitude', type: 'number', format: 'double', example: -74.0721),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'business_name', type: 'string', nullable: true, example: 'Vivero Verde S.A.S.'),
        new OA\Property(property: 'tax_id', type: 'string', nullable: true, example: '900123456-7'),
        new OA\Property(property: 'business_phone', type: 'string', nullable: true),
        new OA\Property(property: 'business_email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'is_premium', type: 'boolean', example: false),
        new OA\Property(property: 'onboarding_completed', type: 'boolean', example: false),
        new OA\Property(property: 'sync_to_map', type: 'boolean', example: false),
        new OA\Property(property: 'products_count', type: 'integer'),
        new OA\Property(property: 'storeProducts', type: 'array', items: new OA\Items(ref: '#/components/schemas/StoreProduct')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class StoreSchema
{
}
