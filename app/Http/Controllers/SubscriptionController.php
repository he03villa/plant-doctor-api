<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Traits\ApiResponseTrait;
use App\Traits\StoreScoped;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class SubscriptionController extends Controller
{
    use ApiResponseTrait, StoreScoped;

    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    #[OA\Get(
        path: '/api/subscriptions/current',
        summary: 'Get current subscription for authenticated store',
        tags: ['Subscriptions'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Current subscription',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'No subscription found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function current(Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($request->user());
            $subscription = $this->subscriptionService->getCurrentSubscription($store);

            if (! $subscription) {
                return $this->notFoundResponse('No subscription found for this store');
            }

            return $this->successResponse(new SubscriptionResource($subscription));
        } catch (Exception $e) {
            return $this->errorResponse('Error getting subscription: '.$e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/api/subscriptions',
        summary: 'Create subscription via Stripe Checkout',
        tags: ['Subscriptions'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreSubscriptionRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Checkout URL returned',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Checkout session created'),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'checkout_url', type: 'string', format: 'uri'),
                            ]
                        ),
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
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = $request->user();
            $store = $this->getStoreForUser($user);

            $plan = Plan::findOrFail($validated['plan_id']);
            $billingCycle = $validated['billing_cycle'] ?? 'monthly';
            $platform = $validated['platform'] ?? 'web';

            $checkoutUrl = $this->subscriptionService->createCheckout($store, $plan, $billingCycle, $platform);

            return $this->successResponse(
                ['checkout_url' => $checkoutUrl],
                'Checkout session created.',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating subscription: '.$e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/api/subscriptions/portal',
        summary: 'Get Stripe Customer Portal URL',
        tags: ['Subscriptions'],
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Portal URL returned',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'portal_url', type: 'string', format: 'uri'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function portal(Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($request->user());
            $portalUrl = $this->subscriptionService->getPortalUrl($store);

            return $this->successResponse(['portal_url' => $portalUrl]);
        } catch (Exception $e) {
            return $this->errorResponse('Error creating portal session: '.$e->getMessage(), 500);
        }
    }

    #[OA\Patch(
        path: '/api/subscriptions/{id}',
        summary: 'Change subscription plan',
        tags: ['Subscriptions'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreSubscriptionRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Subscription updated',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Suscripción actualizada exitosamente.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Subscription not found',
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
    public function update(StoreSubscriptionRequest $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $store = $this->getStoreForUser($user);

            $subscription = Subscription::where('id', $id)
                ->where('store_id', $store->id)
                ->first();

            if (! $subscription) {
                return $this->notFoundResponse('Subscription not found');
            }

            $validated = $request->validated();
            $plan = Plan::findOrFail($validated['plan_id']);
            $billingCycle = $validated['billing_cycle'] ?? 'monthly';

            $result = $this->subscriptionService->changePlan($store, $subscription, $plan, $billingCycle);

            return $this->successResponse(
                new SubscriptionResource($result),
                'Suscripción actualizada exitosamente.'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating subscription: '.$e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/subscriptions/{id}',
        summary: 'Cancel subscription (downgrade to free)',
        tags: ['Subscriptions'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Subscription cancelled',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Suscripción cancelada. Has sido movido al plan Free.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Subscription not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $store = $this->getStoreForUser($user);

            $subscription = Subscription::where('id', $id)
                ->where('store_id', $store->id)
                ->whereIn('status', ['active', 'trialing'])
                ->first();

            if (! $subscription) {
                return $this->notFoundResponse('Active subscription not found');
            }

            $result = $this->subscriptionService->cancel($store, $subscription);

            return $this->successResponse(
                new SubscriptionResource($result),
                'Suscripción cancelada. Has sido movido al plan Free.'
            );
        } catch (Exception $e) {
            return $this->errorResponse('Error cancelling subscription: '.$e->getMessage(), 500);
        }
    }
}
