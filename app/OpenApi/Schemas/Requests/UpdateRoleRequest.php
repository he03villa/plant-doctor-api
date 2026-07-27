<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateRoleRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'editor', nullable: true),
        new OA\Property(
            property: 'permissions',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'plants.create'),
            nullable: true
        ),
    ]
)]
class UpdateRoleRequest {}
