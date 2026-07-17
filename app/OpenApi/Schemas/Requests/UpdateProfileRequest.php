<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateProfileRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
    ]
)]
class UpdateProfileRequest
{
}
