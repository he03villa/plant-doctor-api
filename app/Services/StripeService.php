<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createOrGetCustomer(Store $store): string
    {
        if ($store->stripe_customer_id) {
            return $store->stripe_customer_id;
        }

        $customer = Customer::create([
            'email' => $store->business_email ?? $store->user->email,
            'name' => $store->business_name ?? $store->name,
            'metadata' => [
                'store_id' => $store->id,
                'user_id' => $store->user_id,
            ],
        ]);

        $store->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * @throws ApiErrorException
     */
    public function createCheckoutSession(Store $store, Plan $plan, string $billingCycle = 'monthly', string $platform = 'web'): string
    {
        $stripeCustomerId = $this->createOrGetCustomer($store);
        $priceId = $billingCycle === 'yearly'
            ? $plan->stripe_price_id_yearly
            : $plan->stripe_price_id_monthly;

        if (! $priceId) {
            throw new Exception("Plan {$plan->name} no tiene price ID de Stripe configurado para ciclo {$billingCycle}.");
        }

        $session = CheckoutSession::create([
            'customer' => $stripeCustomerId,
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => config('app.url').'/billing?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.url').'/billing',
            'metadata' => [
                'store_id' => $store->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
            ],
            'subscription_data' => [
                'metadata' => [
                    'store_id' => $store->id,
                    'plan_id' => $plan->id,
                ],
            ],
        ]);

        $store->update([
            'checkout_platform' => $platform,
            'checkout_session_id' => $session->id,
        ]);

        return $session->url;
    }

    /**
     * @throws ApiErrorException
     */
    public function createPortalSession(Store $store): string
    {
        $stripeCustomerId = $this->createOrGetCustomer($store);

        $session = PortalSession::create([
            'customer' => $stripeCustomerId,
            'return_url' => config('app.url').'/billing',
        ]);

        return $session->url;
    }

    /**
     * @throws ApiErrorException
     */
    public function cancelSubscription(Store $store, Subscription $subscription, bool $immediate = false): void
    {
        if (! $subscription->stripe_subscription_id) {
            throw new Exception('La suscripción no tiene un ID de Stripe asociado.');
        }

        if ($immediate) {
            $stripeSub = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
            $stripeSub->cancel();
        } else {
            \Stripe\Subscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
        }
    }

    /**
     * @throws ApiErrorException
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, string $billingCycle = 'monthly'): string
    {
        if (! $subscription->stripe_subscription_id) {
            throw new Exception('La suscripción no tiene un ID de Stripe asociado.');
        }

        $newPriceId = $billingCycle === 'yearly'
            ? $newPlan->stripe_price_id_yearly
            : $newPlan->stripe_price_id_monthly;

        if (! $newPriceId) {
            throw new Exception("Plan {$newPlan->name} no tiene price ID de Stripe configurado.");
        }

        $stripeSub = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);

        \Stripe\Subscription::update($subscription->stripe_subscription_id, [
            'items' => [
                [
                    'id' => $stripeSub->items->data[0]->id,
                    'price' => $newPriceId,
                ],
            ],
            'proration_behavior' => 'create_prorations',
            'metadata' => [
                'store_id' => $subscription->store_id,
                'plan_id' => $newPlan->id,
            ],
        ]);

        $subscription->update([
            'stripe_price_id' => $newPriceId,
            'plan_id' => $newPlan->id,
            'payment_method' => 'stripe',
            'last_payment_amount' => $billingCycle === 'yearly' ? $newPlan->price_yearly : $newPlan->price_monthly,
        ]);

        return $subscription->fresh()->load('plan')->toArray();
    }

    public function constructWebhookEvent(string $payload, string $sigHeader): Event
    {
        return Webhook::constructEvent(
            $payload,
            $sigHeader,
            config('services.stripe.webhook_secret')
        );
    }

    public function handleWebhookEvent(Event $event): void
    {
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event->data->object);
                break;

            case 'invoice.paid':
                $this->handleInvoicePaid($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            default:
                Log::info("Stripe webhook event not handled: {$event->type}");
        }
    }

    private function handleCheckoutCompleted(CheckoutSession $session): void
    {
        $storeId = $session->metadata->store_id ?? $session->metadata?->store_id;
        $planId = $session->metadata->plan_id ?? $session->metadata?->plan_id;

        if (! $storeId || ! $planId) {
            Log::warning('Stripe checkout.session.completed missing metadata', [
                'session_id' => $session->id,
            ]);

            return;
        }

        $store = Store::find($storeId);
        $plan = Plan::find($planId);

        if (! $store || ! $plan) {
            Log::warning('Stripe checkout.session.completed: store or plan not found', [
                'store_id' => $storeId,
                'plan_id' => $planId,
            ]);

            return;
        }

        $stripeSubscriptionId = $session->subscription;

        $stripeSub = \Stripe\Subscription::retrieve($stripeSubscriptionId);

        $billingCycle = $session->metadata->billing_cycle ?? 'monthly';
        $priceId = $billingCycle === 'yearly'
            ? $plan->stripe_price_id_yearly
            : $plan->stripe_price_id_monthly;

        $periodStart = $stripeSub->current_period_start
            ? Carbon::createFromTimestamp($stripeSub->current_period_start)
            : now();
        $periodEnd = $stripeSub->current_period_end
            ? Carbon::createFromTimestamp($stripeSub->current_period_end)
            : ($billingCycle === 'yearly' ? now()->addYear() : now()->addMonth());

        $paymentAmount = $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        DB::transaction(function () use ($store, $plan, $stripeSubscriptionId, $priceId, $periodStart, $periodEnd, $session, $paymentAmount) {
            $store->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled']);

            $subscription = Subscription::create([
                'store_id' => $store->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'payment_method' => 'stripe',
                'last_payment_at' => now(),
                'last_payment_amount' => $paymentAmount,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_price_id' => $priceId,
            ]);

            Payment::create([
                'subscription_id' => $subscription->id,
                'store_id' => $store->id,
                'amount' => $paymentAmount,
                'currency' => strtoupper(config('services.stripe.currency', 'usd')),
                'status' => 'succeeded',
                'description' => 'Plan '.$plan->name.' (checkout)',
                'transaction_id' => $session->payment_intent,
                'stripe_payment_intent_id' => $session->payment_intent,
            ]);

            $store->update([
                'is_premium' => $plan->slug !== 'free',
            ]);
        });
    }

    private function handleInvoicePaid($invoice): void
    {
        $subscriptionId = $invoice->subscription;

        if (! $subscriptionId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $subscription) {
            Log::warning('Stripe invoice.paid: subscription not found', [
                'stripe_subscription_id' => $subscriptionId,
            ]);

            return;
        }

        $stripeSub = \Stripe\Subscription::retrieve($subscriptionId);

        $periodEnd = Carbon::createFromTimestamp($stripeSub->current_period_end);

        DB::transaction(function () use ($subscription, $invoice, $periodEnd) {
            $subscription->update([
                'status' => 'active',
                'current_period_end' => $periodEnd,
                'last_payment_at' => now(),
                'last_payment_amount' => $invoice->amount_paid / 100,
                'payment_method' => 'stripe',
            ]);

            Payment::create([
                'subscription_id' => $subscription->id,
                'store_id' => $subscription->store_id,
                'amount' => $invoice->amount_paid / 100,
                'currency' => strtoupper($invoice->currency),
                'status' => 'succeeded',
                'description' => 'Renovación plan '.$subscription->plan->name,
                'transaction_id' => $invoice->payment_intent,
                'stripe_payment_intent_id' => $invoice->payment_intent,
            ]);

            $subscription->store->update(['is_premium' => true]);
        });
    }

    private function handleInvoicePaymentFailed($invoice): void
    {
        $subscriptionId = $invoice->subscription;

        if (! $subscriptionId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $subscription) {
            return;
        }

        $subscription->update(['status' => 'past_due']);

        Payment::create([
            'subscription_id' => $subscription->id,
            'store_id' => $subscription->store_id,
            'amount' => $invoice->amount_due / 100,
            'currency' => strtoupper($invoice->currency),
            'status' => 'failed',
            'description' => 'Pago fallido plan '.$subscription->plan->name,
            'transaction_id' => $invoice->payment_intent,
            'stripe_payment_intent_id' => $invoice->payment_intent,
        ]);

        Log::warning('Stripe payment failed', [
            'subscription_id' => $subscription->id,
            'store_id' => $subscription->store_id,
        ]);
    }

    private function handleSubscriptionUpdated(\Stripe\Subscription $stripeSub): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSub->id)->first();

        if (! $subscription) {
            return;
        }

        $statusMap = [
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            'unpaid' => 'past_due',
            'incomplete' => 'trialing',
            'trialing' => 'trialing',
        ];

        $newStatus = $statusMap[$stripeSub->status] ?? $subscription->status;

        $updateData = ['status' => $newStatus];

        if ($stripeSub->current_period_end) {
            $updateData['current_period_end'] = Carbon::createFromTimestamp($stripeSub->current_period_end);
        }

        $subscription->update($updateData);

        if (in_array($newStatus, ['cancelled', 'expired'])) {
            $subscription->store->update(['is_premium' => false]);
        }
    }

    private function handleSubscriptionDeleted(\Stripe\Subscription $stripeSub): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSub->id)->first();

        if (! $subscription) {
            return;
        }

        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $freePlan = Plan::where('slug', 'free')->first();

            if ($freePlan) {
                Subscription::create([
                    'store_id' => $subscription->store_id,
                    'plan_id' => $freePlan->id,
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addCentury(),
                ]);
            }

            $subscription->store->update(['is_premium' => false]);
        });
    }
}
