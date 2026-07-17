<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Diagnosis',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'confidence_score', type: 'number', format: 'float', example: 87.5),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'image_url', type: 'string', nullable: true, format: 'uri'),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'ai_provider', type: 'string', nullable: true, example: 'groq', description: 'AI service used for diagnosis'),
        new OA\Property(property: 'species', type: 'object', properties: [
            new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Rosa canina', description: 'Scientific name of the identified species'),
            new OA\Property(property: 'common_names', type: 'array', items: new OA\Items(type: 'string'), example: ['dog rose', 'escaramujo'], description: 'Common names of the species'),
        ]),
        new OA\Property(property: 'disease', type: 'object', properties: [
            new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Black Spot', description: 'Common name of the detected disease'),
            new OA\Property(property: 'scientific_name', type: 'string', nullable: true, example: 'Diplocarpon rosae', description: 'Scientific name of the disease'),
            new OA\Property(property: 'severity', type: 'string', nullable: true, enum: ['low', 'medium', 'high'], example: 'medium'),
            new OA\Property(property: 'symptoms', type: 'array', items: new OA\Items(type: 'string'), example: ['Dark circular spots', 'Yellowing leaves'], description: 'Observed symptoms'),
            new OA\Property(property: 'treatment', type: 'string', nullable: true, description: 'Recommended treatment'),
            new OA\Property(property: 'prevention', type: 'string', nullable: true, description: 'Prevention measures'),
            new OA\Property(property: 'catalog_match', ref: '#/components/schemas/Disease', description: 'Matched disease from local catalog (Perenual)'),
        ]),
        new OA\Property(property: 'expert_verified', type: 'boolean', example: false),
        new OA\Property(property: 'expert_notes', type: 'string', nullable: true),
        new OA\Property(property: 'plant', ref: '#/components/schemas/Plant'),
        new OA\Property(property: 'expert', ref: '#/components/schemas/User'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class DiagnosisSchema
{
}
