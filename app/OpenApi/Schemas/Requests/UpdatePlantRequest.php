<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdatePlantRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Mi Tomate Actualizado'),
        new OA\Property(property: 'species', type: 'string', nullable: true, example: 'Solanum lycopersicum'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Tomate actualizado'),
        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
    ]
)]
class UpdatePlantRequest
{
}
