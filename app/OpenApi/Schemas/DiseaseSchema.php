<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Disease',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Oídio'),
        new OA\Property(property: 'scientific_name', type: 'string', nullable: true, example: 'Erysiphe cichoracearum'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'symptoms', type: 'string', nullable: true),
        new OA\Property(property: 'treatment', type: 'string', nullable: true),
        new OA\Property(property: 'prevention', type: 'string', nullable: true),
        new OA\Property(property: 'image_url', type: 'string', nullable: true, format: 'uri'),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: 'fungal'),
        new OA\Property(property: 'severity', type: 'string', example: 'medium'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'perenual_id', type: 'integer', nullable: true, description: 'Perenual API ID'),
        new OA\Property(property: 'last_synced_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class DiseaseSchema
{
}
