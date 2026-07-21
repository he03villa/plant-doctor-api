<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateStoreRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Vivero Verde'),
        new OA\Property(property: 'address', type: 'string', example: 'Calle 123 #45-67'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'latitude', type: 'number', format: 'double'),
        new OA\Property(property: 'longitude', type: 'number', format: 'double'),
        new OA\Property(property: 'business_name', type: 'string', nullable: true),
        new OA\Property(property: 'tax_id', type: 'string', nullable: true),
        new OA\Property(property: 'business_phone', type: 'string', nullable: true),
        new OA\Property(property: 'business_email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'sync_to_map', type: 'boolean'),
    ]
)]
class UpdateStoreRequest
{
}
