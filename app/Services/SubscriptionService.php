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
     * Assign a plan to a store manually (no Stripe checkout). Used by the
     * backoffice super admin to grant/override a store's subscription.
     *
     * Any previous active/trialing subscription backed by Stripe is cancelled
     * in Stripe first so the customer is not billed twice.
     *
     * @throws ApiErrorException
     */
    public function assignPlan(
        Store $store,
        Plan $plan,
        string $billingCycle = 'monthly',
        string $status = 'active',
        ?int $trialDays = null
    ): Subscription {
        $store->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('stripe_subscription_id')
            ->get()
            ->each(fn (Subscription $existing) => $this->stripeService->cancelSubscription($store, $existing, true));

        return DB::transaction(function () use ($store, $plan, $billingCycle, $status, $trialDays) {
            $store->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $isFree = $plan->slug === 'free';
            $start = now();
            $isTrialing = $status === 'trialing' && ! $isFree;
            $recordsPayment = ! $isFree && ! $isTrialing;

            if ($isTrialing) {
                $end = $start->copy()->addDays(max((int) $trialDays, 1));
            } elseif ($billingCycle === 'yearly') {
                $end = $start->copy()->addYear();
            } else {
                $end = $start->copy()->addMonth();
            }

            $subscription = Subscription::create([
                'store_id' => $store->id,
                'plan_id' => $plan->id,
                'status' => $isFree ? 'active' : $status,
                'trial_ends_at' => $isTrialing ? $end : null,
                'current_period_start' => $start,
                'current_period_end' => $isFree ? $start->copy()->addCentury() : $end,
                'payment_method' => 'manual',
                'last_payment_at' => $recordsPayment ? now() : null,
                'last_payment_amount' => $recordsPayment
                    ? ($billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly)
                    : null,
            ]);

            $store->update(['is_premium' => ! $isFree]);

            return $subscription->load('plan');
        });
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
