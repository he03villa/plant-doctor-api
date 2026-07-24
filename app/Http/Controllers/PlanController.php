<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Exception;

class PlanController extends Controller
{
    use ApiResponseTrait;

    #[OA\Get(
        path: '/api/plans',
        summary: 'List all active plans',
        tags: ['Plans'],
        responses: [
            new OA\Response(response: 200, description: 'Plans listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Plan')),
                    ]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        try {
            $plans = Plan::where('is_active', true)->orderBy('display_order')->get();

            return $this->successResponse(PlanResource::collection($plans));
        } catch (Exception $e) {
            return $this->errorResponse('Error listing plans: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/plans/{slug}',
        summary: 'Get plan by slug',
        tags: ['Plans'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plan retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Plan'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Plan not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function show(string $slug): JsonResponse
    {
        try {
            $plan = Plan::where('slug', $slug)->where('is_active', true)->first();

            if (!$plan) {
                return $this->notFoundResponse('Plan not found');
            }

            return $this->successResponse(new PlanResource($plan));
        } catch (Exception $e) {
            return $this->errorResponse('Error getting plan: ' . $e->getMessage(), 500);
        }
    }
}
