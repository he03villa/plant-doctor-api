<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreOrderRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'invoice_number', type: 'string', example: '1234'),
        new OA\Property(property: 'invoice_date', type: 'string', format: 'date', example: '2024-01-15'),
        new OA\Property(property: 'supplier_name', type: 'string', example: 'Distribuidora ABC'),
        new OA\Property(property: 'subtotal', type: 'number', example: 120000),
        new OA\Property(property: 'tax', type: 'number', example: 19000),
        new OA\Property(property: 'total', type: 'number', example: 139000),
        new OA\Property(property: 'currency', type: 'string', example: 'COP'),
        new OA\Property(property: 'invoice_image_url', type: 'string', nullable: true),
        new OA\Property(property: 'ocr_raw_text', type: 'string', nullable: true),
        new OA\Property(property: 'ocr_confidence', type: 'number', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(
            type: 'object',
            properties: [
                new OA\Property(property: 'product_name', type: 'string'),
                new OA\Property(property: 'quantity', type: 'integer'),
                new OA\Property(property: 'unit_price', type: 'number'),
                new OA\Property(property: 'total_price', type: 'number'),
                new OA\Property(property: 'matched_product_id', type: 'integer', nullable: true),
            ]
        )),
    ]
)]
class StoreOrderRequest
{
}
