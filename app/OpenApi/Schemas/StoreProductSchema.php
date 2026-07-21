<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreProduct',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Fertilizante NPK 10-10-10'),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: 'fertilizante'),
        new OA\Property(property: 'sku', type: 'string', nullable: true, example: 'FERT-NPK-001'),
        new OA\Property(property: 'sale_price', type: 'number', example: 25000),
        new OA\Property(property: 'purchase_price', type: 'number', nullable: true, example: 15000),
        new OA\Property(property: 'stock_quantity', type: 'integer', example: 50),
        new OA\Property(property: 'min_stock', type: 'integer', example: 10),
        new OA\Property(property: 'unit', type: 'string', example: 'unidad'),
        new OA\Property(property: 'barcode', type: 'string', nullable: true, example: '7701234567890'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'is_visible_on_map', type: 'boolean', example: false),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class StoreProductSchema
{
}
