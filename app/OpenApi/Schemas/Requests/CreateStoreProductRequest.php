<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateStoreProductRequest',
    type: 'object',
    required: ['name', 'sale_price'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Arroz premium 1kg'),
        new OA\Property(property: 'sale_price', type: 'number', example: 4500),
        new OA\Property(property: 'stock_quantity', type: 'integer', example: 50),
        new OA\Property(property: 'category', type: 'string', enum: ['alimento', 'electronica', 'ropa', 'hogar', 'deportes', 'herramienta', 'otro']),
        new OA\Property(property: 'sku', type: 'string', nullable: true, example: 'ALIM-ARR-001'),
        new OA\Property(property: 'purchase_price', type: 'number', nullable: true, example: 3200),
        new OA\Property(property: 'min_stock', type: 'integer', example: 10),
        new OA\Property(property: 'unit', type: 'string', example: 'unidad'),
        new OA\Property(property: 'barcode', type: 'string', nullable: true, example: '7701234567890'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Arroz de grano largo, ideal para cocinas comerciales'),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'is_visible_on_map', type: 'boolean', example: false),
    ]
)]
class CreateStoreProductRequest
{
}
