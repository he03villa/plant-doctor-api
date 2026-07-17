<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StorePlantRequest',
    type: 'object',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Mi Tomate'),
        new OA\Property(property: 'species', type: 'string', nullable: true, example: 'Solanum lycopersicum'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Tomate del jardín'),
        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true, example: 19.4326),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true, example: -99.1332),
    ]
)]
class StorePlantRequest
{
}
