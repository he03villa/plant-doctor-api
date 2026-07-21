<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class StoreController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/stores
     * List stores (user's own stores, or all for admin)
     */
    #[OA\Get(
        path: '/api/stores',
        summary: 'List stores',
        tags: ['Stores'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Stores listed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Store')),
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            new OA\Property(property: 'total', type: 'integer', example: 2),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Store::withCount('storeProducts');

        if ($user->hasRole('admin')) {
            $query->with('user');
        } else {
            $query->where('user_id', $user->id);
        }

        $stores = $query->latest()->paginate(15);

        return StoreResource::collection($stores);
    }

    /**
     * POST /api/stores
     * Create a new store
     */
    #[OA\Post(
        path: '/api/stores',
        summary: 'Create a new store',
        tags: ['Stores'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateStoreRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Store created',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Store created'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Store'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Store already exists',
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
    public function store(CreateStoreRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $existingStore = Store::where('user_id', $user->id)->first();
            if ($existingStore) {
                return $this->errorResponse('You already have a store. Update it instead.', 400);
            }

            $validated = $request->validated();
            $validated['user_id'] = $user->id;
            $validated['location'] = DB::raw("ST_SetSRID(ST_MakePoint({$validated['longitude']}, {$validated['latitude']}), 4326)");

            $store = Store::create($validated);

            if (!$user->hasRole('store_owner') && !$user->hasRole('admin')) {
                $user->assignRole('store_owner');
            }

            return $this->successResponse(
                new StoreResource($store->loadCount('storeProducts')),
                'Store created',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating store: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/stores/{store}
     * Show store details
     */
    #[OA\Get(
        path: '/api/stores/{store}',
        summary: 'Get store details',
        tags: ['Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Store retrieved',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Store'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $store = Store::withCount('storeProducts')->find($id);

            if (!$store) {
                return $this->notFoundResponse('Store not found');
            }

            return $this->successResponse(new StoreResource($store));
        } catch (Exception $e) {
            return $this->errorResponse('Error getting store: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/stores/{store}
     * Update a store
     */
    #[OA\Put(
        path: '/api/stores/{store}',
        summary: 'Update a store',
        tags: ['Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateStoreRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Store updated',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Store updated'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Store'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
    public function update(UpdateStoreRequest $request, int $id): JsonResponse
    {
        try {
            $store = Store::find($id);

            if (!$store) {
                return $this->notFoundResponse('Store not found');
            }

            $validated = $request->validated();

            if (isset($validated['latitude']) && isset($validated['longitude'])) {
                $validated['location'] = DB::raw("ST_SetSRID(ST_MakePoint({$validated['longitude']}, {$validated['latitude']}), 4326)");
            }

            $store->update($validated);

            return $this->successResponse(new StoreResource($store->loadCount('storeProducts')), 'Store updated');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating store: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/stores/{store}
     * Delete a store
     */
    #[OA\Delete(
        path: '/api/stores/{store}',
        summary: 'Delete a store',
        tags: ['Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Store deleted',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $store = Store::find($id);

            if (!$store) {
                return $this->notFoundResponse('Store not found');
            }

            $store->delete();

            return $this->successResponse(null, 'Store deleted');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting store: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/stores/{store}/onboarding
     * Mark store onboarding as completed
     */
    #[OA\Put(
        path: '/api/stores/{store}/onboarding',
        summary: 'Complete store onboarding',
        tags: ['Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Onboarding completed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Onboarding completed'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Store'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function onboarding(int $id): JsonResponse
    {
        try {
            $store = Store::find($id);

            if (!$store) {
                return $this->notFoundResponse('Store not found');
            }

            $store->update(['onboarding_completed' => true]);

            return $this->successResponse(new StoreResource($store), 'Onboarding completed');
        } catch (Exception $e) {
            return $this->errorResponse('Error completing onboarding: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/stores/{store}/toggle-map
     * Toggle store visibility on map
     */
    #[OA\Put(
        path: '/api/stores/{store}/toggle-map',
        summary: 'Toggle store map visibility',
        tags: ['Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Map visibility toggled',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Map visibility toggled'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Store'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Store not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function toggleMap(int $id): JsonResponse
    {
        try {
            $store = Store::find($id);

            if (!$store) {
                return $this->notFoundResponse('Store not found');
            }

            $store->update(['sync_to_map' => !$store->sync_to_map]);

            return $this->successResponse(new StoreResource($store), 'Map visibility toggled');
        } catch (Exception $e) {
            return $this->errorResponse('Error toggling map visibility: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/stores/nearby
     * Find nearby stores with optional product search
     */
    #[OA\Get(
        path: '/api/stores/nearby',
        summary: 'Find nearby stores',
        tags: ['Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'latitude', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'double'), description: 'User latitude'),
            new OA\Parameter(name: 'longitude', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'double'), description: 'User longitude'),
            new OA\Parameter(name: 'radius', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 5000), description: 'Search radius in meters'),
            new OA\Parameter(name: 'products', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Comma-separated product names to match'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Nearby stores found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Nearby stores found'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'stores', type: 'array', items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Vivero Verde'),
                                    new OA\Property(property: 'distance_meters', type: 'integer', example: 1200),
                                    new OA\Property(property: 'has_recommended_products', type: 'boolean', example: true),
                                    new OA\Property(property: 'match_count', type: 'integer', example: 2),
                                    new OA\Property(property: 'matching_products', type: 'array', items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'generic_name', type: 'string', example: 'fungicida'),
                                            new OA\Property(property: 'store_product_name', type: 'string', example: 'Fungicida systemic 50ml'),
                                            new OA\Property(property: 'price', type: 'number', example: 18000),
                                        ]
                                    )),
                                    new OA\Property(property: 'missing_products', type: 'array', items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'generic_name', type: 'string', example: 'insecticida'),
                                        ]
                                    )),
                                ]
                            )),
                            new OA\Property(property: 'summary', type: 'object', properties: [
                                new OA\Property(property: 'total_nearby', type: 'integer', example: 5),
                                new OA\Property(property: 'total_with_products', type: 'integer', example: 3),
                                new OA\Property(property: 'searched_products', type: 'array', items: new OA\Items(type: 'string', example: 'fungicida')),
                            ]),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
    public function nearby(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'nullable|integer|min:100|max:50000',
                'products' => 'nullable|string',
            ]);

            $lat = (float) $request->query('latitude');
            $lng = (float) $request->query('longitude');
            $radius = (int) $request->query('radius', 5000);

            $productNames = null;
            if ($request->query('products')) {
                $productNames = collect(explode(',', $request->query('products')))
                    ->map(fn(string $p) => trim($p))
                    ->filter();
            }

            $storeService = app(\App\Services\StoreService::class);
            $results = $storeService->findNearbyWithProducts($lat, $lng, $radius, $productNames);

            return $this->successResponse($results, 'Nearby stores found');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error finding nearby stores: ' . $e->getMessage(), 500);
        }
    }
}
