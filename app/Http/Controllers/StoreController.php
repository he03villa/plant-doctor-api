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
            new OA\Response(response: 200, description: 'Stores listed'),
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
            new OA\Response(response: 201, description: 'Store created'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
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
            new OA\Response(response: 200, description: 'Store retrieved'),
            new OA\Response(response: 404, description: 'Store not found'),
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
            new OA\Response(response: 200, description: 'Store updated'),
            new OA\Response(response: 404, description: 'Store not found'),
            new OA\Response(response: 422, description: 'Validation error'),
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
            new OA\Response(response: 200, description: 'Store deleted'),
            new OA\Response(response: 404, description: 'Store not found'),
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
            new OA\Response(response: 200, description: 'Onboarding completed'),
            new OA\Response(response: 404, description: 'Store not found'),
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
            new OA\Response(response: 200, description: 'Map visibility toggled'),
            new OA\Response(response: 404, description: 'Store not found'),
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
            new OA\Parameter(name: 'latitude', in: 'query', required: true, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'longitude', in: 'query', required: true, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'radius', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 5000)),
            new OA\Parameter(name: 'products', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Comma-separated product names'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Nearby stores found'),
            new OA\Response(response: 422, description: 'Validation error'),
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
