<?php

namespace App\Http\Controllers;

use App\Http\Resources\DiseaseResource;
use App\Models\Disease;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class DiseaseController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/diseases
     * List diseases with search and filter
     */
    #[OA\Get(
        path: '/api/diseases',
        summary: 'List diseases',
        tags: ['Diseases'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search by name, scientific name or description'),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Filter by category'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Diseases listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Disease')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Disease::where('is_active', true);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('scientific_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $diseases = $query->latest()->paginate(15);

        return DiseaseResource::collection($diseases);
    }

    /**
     * GET /api/diseases/{disease}
     * Get disease details
     */
    #[OA\Get(
        path: '/api/diseases/{disease}',
        summary: 'Get disease details',
        tags: ['Diseases'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'disease', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Disease retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Disease'),
                    ]
                )
            ),
        ]
    )]
    public function show(Disease $disease): JsonResponse
    {
        return $this->successResponse(new DiseaseResource($disease));
    }
}
