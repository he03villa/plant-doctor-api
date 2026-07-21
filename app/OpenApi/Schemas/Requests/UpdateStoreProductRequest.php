<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateStoreProductRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'sale_price', type: 'number'),
        new OA\Property(property: 'stock_quantity', type: 'integer'),
        new OA\Property(property: 'category', type: 'string', enum: ['planta', 'fertilizante', 'maceta', 'sustrato', 'herramienta', 'pesticida', 'otro']),
        new OA\Property(property: 'sku', type: 'string', nullable: true),
        new OA\Property(property: 'purchase_price', type: 'number', nullable: true),
        new OA\Property(property: 'min_stock', type: 'integer'),
        new OA\Property(property: 'unit', type: 'string'),
        new OA\Property(property: 'barcode', type: 'string', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'is_visible_on_map', type: 'boolean'),
    ]
)]
class UpdateStoreProductRequest
{
}
