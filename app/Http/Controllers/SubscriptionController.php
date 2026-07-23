<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Exception;

class SubscriptionController extends Controller
{
    use ApiResponseTrait;

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
            $subscription = $store->subscription()->with('plan')->first();

            if (!$subscription) {
                return $this->notFoundResponse('No subscription found for this store');
            }

            return $this->successResponse(new SubscriptionResource($subscription));
        } catch (Exception $e) {
            return $this->errorResponse('Error getting subscription: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/api/subscriptions',
        summary: 'Create or change subscription',
        tags: ['Subscriptions'],
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreSubscriptionRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Subscription created/updated',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Suscripción actualizada exitosamente.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
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
            $isYearly = $billingCycle === 'yearly';

            $result = DB::transaction(function () use ($store, $plan, $isYearly, $user) {
                // Cancel current active subscription if exists
                $currentSubscription = $store->subscription()
                    ->whereIn('status', ['active', 'trialing'])
                    ->first();

                if ($currentSubscription) {
                    $currentSubscription->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ]);
                }

                // Create new subscription
                $periodStart = now();
                $periodEnd = $isYearly ? now()->addYear() : now()->addMonth();

                $subscription = Subscription::create([
                    'store_id' => $store->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'payment_method' => 'manual',
                    'last_payment_at' => now(),
                    'last_payment_amount' => $isYearly ? $plan->price_yearly : $plan->price_monthly,
                ]);

                // Create payment record
                Payment::create([
                    'subscription_id' => $subscription->id,
                    'store_id' => $store->id,
                    'amount' => $isYearly ? $plan->price_yearly : $plan->price_monthly,
                    'currency' => 'USD',
                    'status' => 'succeeded',
                    'description' => 'Plan ' . $plan->name . ' (' . ($isYearly ? 'yearly' : 'monthly') . ')',
                    'transaction_id' => 'manual_' . uniqid(),
                ]);

                // Update store premium flag
                $store->update([
                    'is_premium' => $plan->slug !== 'free',
                ]);

                return $subscription->load('plan');
            });

            return $this->successResponse(
                new SubscriptionResource($result),
                $currentSubscription ? 'Suscripción actualizada exitosamente.' : 'Suscripción creada exitosamente.',
                $currentSubscription ? 200 : 201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error creating subscription: ' . $e->getMessage(), 500);
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

            if (!$subscription) {
                return $this->notFoundResponse('Subscription not found');
            }

            $validated = $request->validated();
            $plan = Plan::findOrFail($validated['plan_id']);
            $billingCycle = $validated['billing_cycle'] ?? 'monthly';
            $isYearly = $billingCycle === 'yearly';

            $subscription->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $isYearly ? now()->addYear() : now()->addMonth(),
                'payment_method' => 'manual',
                'last_payment_at' => now(),
                'last_payment_amount' => $isYearly ? $plan->price_yearly : $plan->price_monthly,
                'cancelled_at' => null,
            ]);

            Payment::create([
                'subscription_id' => $subscription->id,
                'store_id' => $store->id,
                'amount' => $isYearly ? $plan->price_yearly : $plan->price_monthly,
                'currency' => 'USD',
                'status' => 'succeeded',
                'description' => 'Plan change to ' . $plan->name . ' (' . ($isYearly ? 'yearly' : 'monthly') . ')',
                'transaction_id' => 'manual_' . uniqid(),
            ]);

            $store->update([
                'is_premium' => $plan->slug !== 'free',
            ]);

            return $this->successResponse(
                new SubscriptionResource($subscription->load('plan')),
                'Suscripción actualizada exitosamente.'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating subscription: ' . $e->getMessage(), 500);
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

            if (!$subscription) {
                return $this->notFoundResponse('Active subscription not found');
            }

            $freePlan = Plan::where('slug', 'free')->first();

            DB::transaction(function () use ($subscription, $store, $freePlan) {
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'current_period_end' => now(),
                ]);

                // Downgrade to free plan
                if ($freePlan) {
                    Subscription::create([
                        'store_id' => $store->id,
                        'plan_id' => $freePlan->id,
                        'status' => 'active',
                        'current_period_start' => now(),
                        'current_period_end' => now()->addCentury(),
                    ]);
                }

                $store->update(['is_premium' => false]);
            });

            return $this->successResponse(
                new SubscriptionResource($subscription->fresh()->load('plan')),
                'Suscripción cancelada. Has sido movido al plan Free.'
            );
        } catch (Exception $e) {
            return $this->errorResponse('Error cancelling subscription: ' . $e->getMessage(), 500);
        }
    }

    private function getStoreForUser($user): Store
    {
        $store = $user->store;

        if (!$store) {
            throw new Exception('No store found for this user. Create a store first.');
        }

        return $store;
    }
}
