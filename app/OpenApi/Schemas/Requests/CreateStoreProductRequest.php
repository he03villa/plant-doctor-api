<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateStoreProductRequest',
    type: 'object',
    required: ['name', 'sale_price'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Fertilizante NPK 10-10-10'),
        new OA\Property(property: 'sale_price', type: 'number', example: 25000),
        new OA\Property(property: 'stock_quantity', type: 'integer', example: 50),
        new OA\Property(property: 'category', type: 'string', enum: ['planta', 'fertilizante', 'maceta', 'sustrato', 'herramienta', 'pesticida', 'otro']),
        new OA\Property(property: 'sku', type: 'string', nullable: true, example: 'FERT-NPK-001'),
        new OA\Property(property: 'purchase_price', type: 'number', nullable: true, example: 15000),
        new OA\Property(property: 'min_stock', type: 'integer', example: 10),
        new OA\Property(property: 'unit', type: 'string', example: 'unidad'),
        new OA\Property(property: 'barcode', type: 'string', nullable: true, example: '7701234567890'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Fertilizante granulado para plantas de jardín'),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'is_visible_on_map', type: 'boolean', example: false),
    ]
)]
class CreateStoreProductRequest
{
}
