<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AdminCreateUserRequest',
    type: 'object',
    required: ['name', 'email', 'password', 'role'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
        new OA\Property(property: 'role', type: 'string', example: 'store_owner'),
    ]
)]
class AdminCreateUserRequest {}
