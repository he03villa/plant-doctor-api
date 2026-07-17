<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Plant',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Mi Tomate'),
        new OA\Property(property: 'species', type: 'string', nullable: true, example: 'Solanum lycopersicum'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Tomate del jardín'),
        new OA\Property(property: 'image_url', type: 'string', nullable: true, format: 'uri'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'country_name', type: 'string', nullable: true),
        new OA\Property(property: 'state_name', type: 'string', nullable: true),
        new OA\Property(property: 'city_name', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'diagnoses_count', type: 'integer', example: 3),
        new OA\Property(
            property: 'diagnoses',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Diagnosis')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class PlantSchema
{
}
