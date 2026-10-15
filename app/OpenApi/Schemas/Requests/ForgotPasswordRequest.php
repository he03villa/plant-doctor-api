<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ForgotPasswordRequest',
    type: 'object',
    required: ['email'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'platform', type: 'string', enum: ['web', 'mobile'], default: 'web', description: 'Where the reset link will point: web URL or mobile deep link'),
    ]
)]
class ForgotPasswordRequest {}
