<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserRolesPermissionsResource;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * POST /api/auth/register
     * Register a new user
     */
    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register a new user',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'User registered successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AuthResponse'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return $this->successResponse(
                new AuthResource($result),
                'User registered successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error registering user: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/auth/login
     * Login user and return JWT token
     */
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Login user',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AuthResponse'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            if (! $result) {
                return $this->unauthorizedResponse('Invalid credentials');
            }

            return $this->successResponse(
                new AuthResource($result),
                'Login successful'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error logging in: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/auth/logout
     * Invalidate JWT token
     */
    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Logout user',
        tags: ['Auth'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function logout(): JsonResponse
    {
        try {
            $this->authService->logout();

            return $this->successResponse(null, 'Logged out successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error logging out: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/auth/refresh
     * Refresh JWT token
     */
    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Refresh JWT token',
        tags: ['Auth'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token refreshed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/TokenResponse'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function refresh(): JsonResponse
    {
        try {
            $result = $this->authService->refresh();

            return $this->successResponse(
                new TokenResource($result),
                'Token refreshed'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error refreshing token: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/auth/me
     * Get authenticated user
     */
    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Get authenticated user',
        tags: ['Auth'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'User retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function me(): JsonResponse
    {
        try {
            $user = $this->authService->me();

            return $this->successResponse(new UserResource($user));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error getting user: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/auth/me/roles-and-permissions
     * Get authenticated user roles and permissions
     */
    #[OA\Get(
        path: '/api/auth/me/roles-and-permissions',
        summary: 'Get user roles and permissions',
        tags: ['Auth'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Roles and permissions retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', example: 'super_admin')),
                            new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string', example: 'plants.create')),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function meRolesAndPermissions(): JsonResponse
    {
        try {
            $user = $this->authService->meRolesAndPermissions();

            return $this->successResponse(new UserRolesPermissionsResource($user));
        } catch (Exception $e) {
            return $this->errorResponse('Error getting roles and permissions: '.$e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/auth/profile
     * Update authenticated user profile
     */
    #[OA\Put(
        path: '/api/auth/profile',
        summary: 'Update user profile',
        tags: ['Auth'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateProfileRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->updateProfile($request->validated());

            return $this->successResponse(new UserResource($user), 'Profile updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating profile: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/auth/forgot-password
     * Request a password reset link
     */
    #[OA\Post(
        path: '/api/auth/forgot-password',
        summary: 'Request a password reset link',
        description: 'Sends a password reset link to the given email. Always returns 200 to avoid user enumeration.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ForgotPasswordRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Reset link sent (if the email exists)',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 429, description: 'Too many requests',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->forgotPassword($request->validated());

            return $this->successResponse(
                null,
                'If that email address is in our system, we have sent a password reset link'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error sending password reset link: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/auth/reset-password
     * Reset password using a token
     */
    #[OA\Post(
        path: '/api/auth/reset-password',
        summary: 'Reset password with token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ResetPasswordRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'Invalid or expired token',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 429, description: 'Too many requests',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->authService->resetPassword($request->validated());

            if ($status === Password::PASSWORD_RESET) {
                return $this->successResponse(null, 'Password has been reset successfully');
            }

            return $this->errorResponse($this->resetPasswordErrorMessage($status), 400);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error resetting password: '.$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/auth/change-password
     * Change authenticated user password
     */
    #[OA\Post(
        path: '/api/auth/change-password',
        summary: 'Change authenticated user password',
        description: 'Invalidates the current JWT token and returns a new one.',
        tags: ['Auth'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangePasswordRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed, new token issued',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/TokenResponse'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->changePassword($request->validated());

            return $this->successResponse(
                new TokenResource($result),
                'Password changed successfully'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error changing password: '.$e->getMessage(), 500);
        }
    }

    private function resetPasswordErrorMessage(string $status): string
    {
        return match ($status) {
            Password::RESET_THROTTLED => 'Too many reset attempts, please try again later',
            default => 'Invalid or expired password reset token',
        };
    }
}
