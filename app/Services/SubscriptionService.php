<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use Exception;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;

class SubscriptionService
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    public function getCurrentSubscription(Store $store): ?Subscription
    {
        return $store->subscription()->with('plan')->first();
    }

    public function createFreeSubscription(Store $store): Subscription
    {
        $freePlan = Plan::where('slug', 'free')->first();

        if (! $freePlan) {
            throw new Exception('Plan Free no encontrado.');
        }

        return DB::transaction(function () use ($store, $freePlan) {
            $store->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled']);

            $subscription = Subscription::create([
                'store_id' => $store->id,
                'plan_id' => $freePlan->id,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addCentury(),
            ]);

            $store->update(['is_premium' => false]);

            return $subscription->load('plan');
        });
    }

    /**
     * @throws ApiErrorException
     */
    public function createCheckout(Store $store, Plan $plan, string $billingCycle = 'monthly', string $platform = 'web'): string
    {
        if ($plan->slug === 'free') {
            $this->createFreeSubscription($store);

            return config('app.url').'/billing?success=1';
        }

        $existingActive = $store->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('stripe_subscription_id')
            ->first();

        if ($existingActive) {
            $this->stripeService->cancelSubscription($store, $existingActive, true);
        }

        return $this->stripeService->createCheckoutSession($store, $plan, $billingCycle, $platform);
    }

    /**
     * @throws ApiErrorException
     */
    public function getPortalUrl(Store $store): string
    {
        return $this->stripeService->createPortalSession($store);
    }

    /**
     * @throws ApiErrorException
     */
    public function changePlan(Store $store, Subscription $subscription, Plan $newPlan, string $billingCycle = 'monthly'): Subscription
    {
        if ($newPlan->slug === 'free') {
            $this->stripeService->cancelSubscription($store, $subscription, false);

            return $this->createFreeSubscription($store);
        }

        if ($subscription->stripe_subscription_id) {
            $this->stripeService->changePlan($subscription, $newPlan, $billingCycle);
        } else {
            return Subscription::create([
                'store_id' => $store->id,
                'plan_id' => $newPlan->id,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'payment_method' => 'manual',
                'last_payment_at' => now(),
                'last_payment_amount' => $billingCycle === 'yearly' ? $newPlan->price_yearly : $newPlan->price_monthly,
            ]);
        }

        return $subscription->fresh()->load('plan');
    }

    /**
     * @throws ApiErrorException
     */
    public function cancel(Store $store, Subscription $subscription): Subscription
    {
        if ($subscription->stripe_subscription_id) {
            $this->stripeService->cancelSubscription($store, $subscription, false);
        }

        return $this->createFreeSubscription($store);
    }
}
