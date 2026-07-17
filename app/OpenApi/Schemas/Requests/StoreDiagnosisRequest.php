<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreDiagnosisRequest',
    type: 'object',
    required: ['plant_id', 'image'],
    properties: [
        new OA\Property(property: 'plant_id', type: 'integer', example: 1),
        new OA\Property(property: 'image', type: 'string', format: 'binary'),
        new OA\Property(property: 'organ', type: 'string', enum: ['leaf', 'flower', 'fruit', 'bark'], default: 'leaf', nullable: true, description: 'Plant organ photographed for Pl@ntNet identification'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Hojas amarillas'),
    ]
)]
class StoreDiagnosisRequest
{
}
