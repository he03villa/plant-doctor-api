<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreSaleRequest',
    type: 'object',
    required: ['payment_method', 'items'],
    properties: [
        new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'card', 'transfer'], example: 'cash'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Venta realizada con descuento'),
        new OA\Property(property: 'items', type: 'array', minItems: 1, items: new OA\Items(
            type: 'object',
            required: ['product_id', 'quantity', 'unit_price'],
            properties: [
                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                new OA\Property(property: 'quantity', type: 'integer', minimum: 1, example: 2),
                new OA\Property(property: 'unit_price', type: 'number', minimum: 0, example: 45000),
            ]
        )),
    ]
)]
class StoreSaleRequest
{
}
