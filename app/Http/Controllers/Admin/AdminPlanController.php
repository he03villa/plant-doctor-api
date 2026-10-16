<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AdminPlanController extends Controller
{
    use ApiResponseTrait;

    #[OA\Get(
        path: '/api/admin/plans',
        summary: 'List all plans (admin)',
        tags: ['Admin - Plans'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plans listed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 15), 50);
            $plans = Plan::orderBy('display_order')->orderBy('id')->paginate($perPage);

            return PlanResource::collection($plans);
        } catch (Exception $e) {
            return $this->errorResponse('Error listing plans: '.$e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/plans/{plan}',
        summary: 'Get a plan (admin)',
        tags: ['Admin - Plans'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'plan', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plan retrieved'),
            new OA\Response(response: 404, description: 'Plan not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $plan = Plan::find($id);

            if (! $plan) {
                return $this->notFoundResponse('Plan not found');
            }

            return $this->successResponse(new PlanResource($plan));
        } catch (Exception $e) {
            return $this->errorResponse('Error fetching plan: '.$e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/api/admin/plans',
        summary: 'Create a plan (admin)',
        tags: ['Admin - Plans'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['name', 'slug', 'price_monthly', 'features'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Gold'),
                    new OA\Property(property: 'slug', type: 'string', example: 'gold'),
                    new OA\Property(property: 'price_monthly', type: 'number', format: 'float', example: 19.99),
                    new OA\Property(property: 'price_yearly', type: 'number', format: 'float', example: 199.99),
                    new OA\Property(property: 'features', type: 'object'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'display_order', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Plan created'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function store(StorePlanRequest $request): JsonResponse
    {
        try {
            $plan = Plan::create($request->validated());

            return $this->successResponse(new PlanResource($plan), 'Plan created', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating plan: '.$e->getMessage(), 500);
        }
    }

    #[OA\Patch(
        path: '/api/admin/plans/{plan}',
        summary: 'Update a plan (admin)',
        tags: ['Admin - Plans'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'plan', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'price_monthly', type: 'number', format: 'float'),
                    new OA\Property(property: 'price_yearly', type: 'number', format: 'float'),
                    new OA\Property(property: 'features', type: 'object'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'display_order', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Plan updated'),
            new OA\Response(response: 404, description: 'Plan not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdatePlanRequest $request, int $id): JsonResponse
    {
        try {
            $plan = Plan::find($id);

            if (! $plan) {
                return $this->notFoundResponse('Plan not found');
            }

            $plan->update($request->validated());

            return $this->successResponse(new PlanResource($plan), 'Plan updated');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating plan: '.$e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/admin/plans/{plan}',
        summary: 'Delete a plan (only if it has no subscriptions)',
        tags: ['Admin - Plans'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'plan', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plan deleted'),
            new OA\Response(response: 400, description: 'Plan has subscriptions — deactivate it instead'),
            new OA\Response(response: 404, description: 'Plan not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $plan = Plan::find($id);

            if (! $plan) {
                return $this->notFoundResponse('Plan not found');
            }

            if ($plan->subscriptions()->exists()) {
                return $this->errorResponse('Plan has subscriptions. Deactivate it instead of deleting it.', 400);
            }

            $plan->delete();

            return $this->successResponse(null, 'Plan deleted');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting plan: '.$e->getMessage(), 500);
        }
    }
}
