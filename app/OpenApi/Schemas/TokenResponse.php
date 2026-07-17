<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TokenResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
    ]
)]
class TokenResponse
{
}
