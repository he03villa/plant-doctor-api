<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReviewDiagnosisRequest',
    type: 'object',
    required: ['expert_verified'],
    properties: [
        new OA\Property(property: 'expert_verified', type: 'boolean', example: true),
        new OA\Property(property: 'expert_notes', type: 'string', nullable: true, example: 'Confirmed: early blight infection'),
    ]
)]
class ReviewDiagnosisRequest {}
