<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Services\RoleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class PermissionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private RoleService $roleService
    ) {}

    /**
     * GET /api/admin/permissions
     * List all permissions
     */
    #[OA\Get(
        path: '/api/admin/permissions',
        summary: 'List all permissions',
        tags: ['Admin - Permissions'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Permissions listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Permission')),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $permissions = $this->roleService->listPermissions();

        return PermissionResource::collection($permissions);
    }
}
