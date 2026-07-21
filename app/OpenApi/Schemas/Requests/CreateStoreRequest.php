<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateStoreRequest',
    type: 'object',
    required: ['name', 'address', 'latitude', 'longitude'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Vivero Verde'),
        new OA\Property(property: 'address', type: 'string', example: 'Calle 123 #45-67'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+57 300 1234567'),
        new OA\Property(property: 'latitude', type: 'number', format: 'double', example: 4.7110),
        new OA\Property(property: 'longitude', type: 'number', format: 'double', example: -74.0721),
        new OA\Property(property: 'business_name', type: 'string', nullable: true, example: 'Vivero Verde S.A.S.'),
        new OA\Property(property: 'tax_id', type: 'string', nullable: true, example: '900123456-7'),
        new OA\Property(property: 'business_phone', type: 'string', nullable: true, example: '+57 300 7654321'),
        new OA\Property(property: 'business_email', type: 'string', format: 'email', nullable: true, example: 'contacto@viveroverde.com'),
        new OA\Property(property: 'sync_to_map', type: 'boolean', example: false),
    ]
)]
class CreateStoreRequest
{
}
