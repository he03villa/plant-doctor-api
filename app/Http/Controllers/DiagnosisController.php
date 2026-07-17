<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\Plant;
use App\Services\DiagnosisService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class DiagnosisController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private DiagnosisService $diagnosisService
    ) {}

    /**
     * GET /api/diagnoses
     * List user diagnoses
     */
    #[OA\Get(
        path: '/api/diagnoses',
        summary: 'List user diagnoses',
        tags: ['Diagnoses'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Diagnoses listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Diagnosis')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $diagnoses = Diagnosis::where('user_id', $request->user()->id)
            ->with(['plant', 'disease'])
            ->latest()
            ->paginate(15);

        return DiagnosisResource::collection($diagnoses);
    }

    /**
     * POST /api/diagnoses
     * Create a new diagnosis
     */
    #[OA\Post(
        path: '/api/diagnoses',
        summary: 'Create a new diagnosis',
        tags: ['Diagnoses'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/StoreDiagnosisRequest')
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Diagnosis created',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Diagnosis'),
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
    public function store(StoreDiagnosisRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $plant = null;
            if (!empty($validated['plant_id'])) {
                $plant = Plant::findOrFail($validated['plant_id']);
            }

            $diagnosis = $this->diagnosisService->create(
                $request->user(),
                $plant,
                $validated
            );

            return $this->successResponse(new DiagnosisResource($diagnosis->load(['plant', 'disease'])), 'Diagnosis created', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating diagnosis: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/diagnoses/{diagnosis}
     * Get diagnosis details
     */
    #[OA\Get(
        path: '/api/diagnoses/{diagnosis}',
        summary: 'Get diagnosis details',
        tags: ['Diagnoses'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'diagnosis', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Diagnosis retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Diagnosis'),
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
    public function show(Diagnosis $diagnosis): JsonResponse
    {
        try {
            $diagnosis->load(['plant', 'disease', 'expert']);

            return $this->successResponse(new DiagnosisResource($diagnosis));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error getting diagnosis: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/diagnoses/{diagnosis}/request-expert-review
     * Request expert review for a diagnosis
     */
    #[OA\Post(
        path: '/api/diagnoses/{diagnosis}/request-expert-review',
        summary: 'Request expert review',
        tags: ['Diagnoses'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'diagnosis', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Expert review requested',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Diagnosis'),
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
    public function requestExpertReview(Diagnosis $diagnosis): JsonResponse
    {
        try {
            $diagnosis = $this->diagnosisService->requestExpertReview($diagnosis);

            return $this->successResponse(new DiagnosisResource($diagnosis), 'Expert review requested');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error requesting expert review: ' . $e->getMessage(), 500);
        }
    }
}
