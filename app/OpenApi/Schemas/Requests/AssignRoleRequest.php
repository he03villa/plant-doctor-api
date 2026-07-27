<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AssignRoleRequest',
    type: 'object',
    required: ['role'],
    properties: [
        new OA\Property(property: 'role', type: 'string', example: 'super_admin'),
    ]
)]
class AssignRoleRequest {}
