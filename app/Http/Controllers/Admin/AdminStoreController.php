<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAssignSubscriptionRequest;
use App\Http\Resources\StoreResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Store;
use App\Services\SubscriptionService;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AdminStoreController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    #[OA\Get(
        path: '/api/admin/stores',
        summary: 'List all stores (admin)',
        tags: ['Admin - Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'active', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Stores listed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 15), 50);
            $search = $request->query('search');
            $active = $request->query('active');

            $stores = Store::withCount('storeProducts')
                ->with('subscription.plan')
                ->when($search, function ($q, $s) {
                    $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                        ->orWhere('business_name', 'like', "%{$s}%"));
                })
                ->when($active !== null, function ($q) use ($active) {
                    $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return StoreResource::collection($stores);
        } catch (Exception $e) {
            return $this->errorResponse('Error listing stores: '.$e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/stores/{id}',
        summary: 'Get a store (admin)',
        tags: ['Admin - Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Store retrieved'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Store not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $store = Store::with(['subscription.plan', 'storeProducts', 'user'])->find($id);

            if (! $store) {
                return $this->notFoundResponse('Store not found');
            }

            return $this->successResponse(new StoreResource($store));
        } catch (Exception $e) {
            return $this->errorResponse('Error fetching store: '.$e->getMessage(), 500);
        }
    }

    #[OA\Patch(
        path: '/api/admin/stores/{id}/toggle',
        summary: 'Toggle store active status',
        tags: ['Admin - Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Store status updated'),
            new OA\Response(response: 404, description: 'Store not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $store = Store::find($id);

            if (! $store) {
                return $this->notFoundResponse('Store not found');
            }

            $store->is_active = ! $store->is_active;
            $store->save();

            return $this->successResponse(new StoreResource($store), 'Store status updated');
        } catch (Exception $e) {
            return $this->errorResponse('Error updating store: '.$e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/api/admin/stores/{id}/subscription',
        summary: 'Assign or change a store subscription plan (admin, no Stripe)',
        tags: ['Admin - Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['plan_id'],
                properties: [
                    new OA\Property(property: 'plan_id', type: 'integer', example: 2),
                    new OA\Property(property: 'billing_cycle', type: 'string', enum: ['monthly', 'yearly']),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'trialing']),
                    new OA\Property(property: 'trial_days', type: 'integer', example: 14),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Subscription assigned'),
            new OA\Response(response: 404, description: 'Store not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function assignSubscription(AdminAssignSubscriptionRequest $request, int $id): JsonResponse
    {
        try {
            $store = Store::find($id);

            if (! $store) {
                return $this->notFoundResponse('Store not found');
            }

            $validated = $request->validated();
            $plan = Plan::find($validated['plan_id']);

            if (! $plan) {
                return $this->notFoundResponse('Plan not found');
            }

            $subscription = $this->subscriptionService->assignPlan(
                $store,
                $plan,
                $validated['billing_cycle'] ?? 'monthly',
                $validated['status'] ?? 'active',
                $validated['trial_days'] ?? null
            );

            return $this->successResponse(new SubscriptionResource($subscription), 'Suscripción asignada');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error assigning subscription: '.$e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/admin/stores/{id}',
        summary: 'Delete a store (only if it has no sales or subscription history)',
        tags: ['Admin - Stores'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Store deleted'),
            new OA\Response(response: 400, description: 'Store has data — deactivate it instead'),
            new OA\Response(response: 404, description: 'Store not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $store = Store::find($id);

            if (! $store) {
                return $this->notFoundResponse('Store not found');
            }

            if ($store->sales()->exists() || $store->subscriptions()->exists()) {
                return $this->errorResponse('Store has sales or subscription history. Deactivate it instead of deleting it.', 400);
            }

            $store->storeProducts()->delete();
            $store->orders()->delete();
            $store->delete();

            return $this->successResponse(null, 'Store deleted');
        } catch (Exception $e) {
            return $this->errorResponse('Error deleting store: '.$e->getMessage(), 500);
        }
    }
}
