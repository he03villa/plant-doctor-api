<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantRequest;
use App\Http\Requests\UpdatePlantRequest;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use App\Services\PlantService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class PlantController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PlantService $plantService
    ) {}

    /**
     * GET /api/plants
     * List user plants
     */
    #[OA\Get(
        path: '/api/plants',
        summary: 'List user plants',
        tags: ['Plants'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Plants listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Plant')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $plants = Plant::where('user_id', $request->user()->id)
            ->with('diagnoses')
            ->latest()
            ->paginate(15);

        return PlantResource::collection($plants);
    }

    /**
     * POST /api/plants
     * Create a new plant
     */
    #[OA\Post(
        path: '/api/plants',
        summary: 'Create a new plant',
        tags: ['Plants'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/StorePlantRequest')
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Plant created',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Plant'),
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
    public function store(StorePlantRequest $request): JsonResponse
    {
        try {
            $plant = $this->plantService->create($request->user(), $request->validated());

            return $this->successResponse(new PlantResource($plant->load('diagnoses')), 'Plant created', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating plant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/plants/{plant}
     * Get plant details
     */
    #[OA\Get(
        path: '/api/plants/{plant}',
        summary: 'Get plant details',
        tags: ['Plants'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'plant', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plant retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Plant'),
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
    public function show(Plant $plant): JsonResponse
    {
        try {
            //$this->authorize('view', $plant);

            $plant->load('diagnoses.disease');

            return $this->successResponse(new PlantResource($plant));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error getting plant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/plants/{plant}
     * Update a plant
     */
    #[OA\Put(
        path: '/api/plants/{plant}',
        summary: 'Update a plant',
        tags: ['Plants'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'plant', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/UpdatePlantRequest')
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Plant updated',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Plant'),
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
    public function update(UpdatePlantRequest $request, Plant $plant): JsonResponse
    {
        try {
            //$this->authorize('update', $plant);

            $plant = $this->plantService->update($plant, $request->validated());

            return $this->successResponse(new PlantResource($plant), 'Plant updated');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating plant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/plants/{plant}
     * Delete a plant
     */
    #[OA\Delete(
        path: '/api/plants/{plant}',
        summary: 'Delete a plant',
        tags: ['Plants'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'plant', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plant deleted',
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
    public function destroy(Plant $plant): JsonResponse
    {
        try {
            //$this->authorize('delete', $plant);

            $this->plantService->delete($plant);

            return $this->successResponse(null, 'Plant deleted');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting plant: ' . $e->getMessage(), 500);
        }
    }
}
