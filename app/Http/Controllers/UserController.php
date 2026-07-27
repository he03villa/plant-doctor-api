<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCreateUserRequest;
use App\Http\Requests\AdminUpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private UserService $userService
    ) {}

    /**
     * GET /api/admin/users
     * List all users
     */
    #[OA\Get(
        path: '/api/admin/users',
        summary: 'List all users',
        tags: ['Admin - Users'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search by name or email'),
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Filter by role name'),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                            new OA\Property(property: 'current_page', type: 'integer'),
                            new OA\Property(property: 'per_page', type: 'integer'),
                            new OA\Property(property: 'total', type: 'integer'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = $this->userService->list(
            search: $request->query('search'),
            role: $request->query('role'),
            perPage: (int) $request->query('per_page', 15)
        );

        return UserResource::collection($users);
    }

    /**
     * POST /api/admin/users
     * Create a new user
     */
    #[OA\Post(
        path: '/api/admin/users',
        summary: 'Create a new user',
        tags: ['Admin - Users'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AdminCreateUserRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created',
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
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function store(AdminCreateUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->create($request->validated());

            return $this->successResponse(
                new UserResource($user),
                'User created successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating user: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/users/{id}
     * Get user details
     */
    #[OA\Get(
        path: '/api/admin/users/{id}',
        summary: 'Get user details',
        tags: ['Admin - Users'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->userService->getById($id);

            return $this->successResponse(new UserResource($user));
        } catch (Exception $e) {
            return $this->notFoundResponse('User not found');
        }
    }

    /**
     * PUT /api/admin/users/{id}
     * Update a user
     */
    #[OA\Put(
        path: '/api/admin/users/{id}',
        summary: 'Update a user',
        tags: ['Admin - Users'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AdminUpdateUserRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'User updated',
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
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function update(AdminUpdateUserRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->update($id, $request->validated());

            return $this->successResponse(
                new UserResource($user),
                'User updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating user: '.$e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/users/{id}
     * Delete a user (soft delete)
     */
    #[OA\Delete(
        path: '/api/admin/users/{id}',
        summary: 'Delete a user (soft delete)',
        tags: ['Admin - Users'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User deleted'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->userService->delete($id);

            return $this->successResponse(null, 'User deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting user: '.$e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/admin/users/{id}/toggle
     * Toggle user active status
     */
    #[OA\Patch(
        path: '/api/admin/users/{id}/toggle',
        summary: 'Toggle user active status',
        tags: ['Admin - Users'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User status toggled',
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
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $user = $this->userService->toggleActive($id);
            $status = $user->is_active ? 'activated' : 'deactivated';

            return $this->successResponse(
                new UserResource($user),
                "User {$status} successfully"
            );
        } catch (Exception $e) {
            return $this->errorResponse('Error toggling user status: '.$e->getMessage(), 500);
        }
    }
}
