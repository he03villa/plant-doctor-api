<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'current_period_start' => $this->current_period_start?->toISOString(),
            'current_period_end' => $this->current_period_end?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'payment_method' => $this->payment_method,
            'last_payment_at' => $this->last_payment_at?->toISOString(),
            'last_payment_amount' => $this->last_payment_amount ? (float) $this->last_payment_amount : null,
            'days_until_renewal' => $this->days_until_renewal,
            'is_trial' => $this->is_trial,
            'is_active' => $this->status === 'active',
        ];
    }
}
