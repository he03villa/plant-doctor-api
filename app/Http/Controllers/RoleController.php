<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Services\RoleService;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private RoleService $roleService
    ) {}

    private function authorizeSuperAdmin(Request $request): ?JsonResponse
    {
        if (! $request->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Unauthorized');
        }

        return null;
    }

    /**
     * GET /api/admin/roles
     * List all roles
     */
    #[OA\Get(
        path: '/api/admin/roles',
        summary: 'List all roles',
        tags: ['Admin - Roles'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Roles listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Role')),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = $this->roleService->list();

        return RoleResource::collection($roles);
    }

    /**
     * POST /api/admin/roles
     * Create a new role
     */
    #[OA\Post(
        path: '/api/admin/roles',
        summary: 'Create a new role',
        tags: ['Admin - Roles'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreRoleRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Role created',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->roleService->create($request->validated());

            return $this->successResponse(
                new RoleResource($role),
                'Role created successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating role: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/roles/{id}
     * Get role details
     */
    #[OA\Get(
        path: '/api/admin/roles/{id}',
        summary: 'Get role details',
        tags: ['Admin - Roles'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getById($id);

            return $this->successResponse(new RoleResource($role));
        } catch (Exception $e) {
            return $this->notFoundResponse('Role not found');
        }
    }

    /**
     * PUT /api/admin/roles/{id}
     * Update a role
     */
    #[OA\Put(
        path: '/api/admin/roles/{id}',
        summary: 'Update a role',
        tags: ['Admin - Roles'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateRoleRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role updated',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        try {
            $role = $this->roleService->update($id, $request->validated());

            return $this->successResponse(
                new RoleResource($role),
                'Role updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating role: '.$e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/roles/{id}
     * Delete a role
     */
    #[OA\Delete(
        path: '/api/admin/roles/{id}',
        summary: 'Delete a role',
        tags: ['Admin - Roles'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role deleted'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->roleService->delete($id);

            return $this->successResponse(null, 'Role deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting role: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/users/{id}/roles
     * Assign role to user
     */
    #[OA\Post(
        path: '/api/admin/users/{id}/roles',
        summary: 'Assign role to user',
        tags: ['Admin - Roles'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AssignRoleRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role assigned',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'User or role not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function assignToUser(AssignRoleRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->roleService->assignToUser($id, $request->validated()['role']);

            return $this->successResponse(
                new UserResource($user),
                'Role assigned successfully'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error assigning role: '.$e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/users/{id}/roles/{role}
     * Remove role from user
     */
    #[OA\Delete(
        path: '/api/admin/users/{id}/roles/{role}',
        summary: 'Remove role from user',
        tags: ['Admin - Roles'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role removed'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function removeFromUser(int $id, string $role): JsonResponse
    {
        try {
            $user = $this->roleService->removeFromUser($id, $role);

            return $this->successResponse(
                new UserResource($user),
                'Role removed successfully'
            );
        } catch (Exception $e) {
            return $this->errorResponse('Error removing role: '.$e->getMessage(), 500);
        }
    }
}
