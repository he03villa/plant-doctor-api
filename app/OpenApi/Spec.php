<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Plant Doctor API',
    version: '1.0.0',
    description: 'API for plant disease diagnosis and health tracking'
)]
#[OA\Server(
    url: SWAGGER_SERVER_HOST,
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'jwt',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(name: 'Auth', description: 'Authentication and user management')]
#[OA\Tag(name: 'Plants', description: 'User plant management')]
#[OA\Tag(name: 'Diseases', description: 'Disease catalog')]
#[OA\Tag(name: 'Diagnoses', description: 'Plant diagnoses and expert review')]
class Spec
{
}
