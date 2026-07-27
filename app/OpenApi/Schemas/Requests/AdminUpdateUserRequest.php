<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AdminUpdateUserRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'John Doe', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com', nullable: true),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newpassword123', nullable: true),
    ]
)]
class AdminUpdateUserRequest {}
