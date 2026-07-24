<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Sale',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'invoice_number', type: 'string', example: 'V-000001'),
        new OA\Property(property: 'subtotal', type: 'number', example: 90000),
        new OA\Property(property: 'tax', type: 'number', example: 0),
        new OA\Property(property: 'total', type: 'number', example: 90000),
        new OA\Property(property: 'currency', type: 'string', example: 'COP'),
        new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'card', 'transfer'], example: 'cash'),
        new OA\Property(property: 'status', type: 'string', enum: ['completed', 'cancelled'], example: 'completed'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'store', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Vivero Verde'),
        ]),
        new OA\Property(property: 'user', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Juan Perez'),
        ]),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'product_name', type: 'string', example: 'Ficus benjamina'),
                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                new OA\Property(property: 'unit_price', type: 'number', example: 45000),
                new OA\Property(property: 'total_price', type: 'number', example: 90000),
                new OA\Property(property: 'product', type: 'object', nullable: true, properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Ficus benjamina'),
                    new OA\Property(property: 'sale_price', type: 'number', example: 45000),
                ]),
            ]
        )),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class SaleSchema
{
}
