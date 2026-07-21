<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderItem',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'product_name', type: 'string', example: 'Jabón potásico 500ml'),
        new OA\Property(property: 'quantity', type: 'integer', example: 10),
        new OA\Property(property: 'unit_price', type: 'number', example: 12000),
        new OA\Property(property: 'total_price', type: 'number', example: 120000),
        new OA\Property(property: 'matched_product', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'sale_price', type: 'number'),
        ]),
    ]
)]
class OrderItemSchema
{
}
