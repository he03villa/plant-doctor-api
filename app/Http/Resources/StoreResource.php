<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
            'business_name' => $this->business_name,
            'tax_id' => $this->tax_id,
            'business_phone' => $this->business_phone,
            'business_email' => $this->business_email,
            'is_premium' => $this->is_premium,
            'onboarding_completed' => $this->onboarding_completed,
            'sync_to_map' => $this->sync_to_map,
            'products_count' => $this->whenCounted('storeProducts'),
            'storeProducts' => StoreProductResource::collection($this->whenLoaded('storeProducts')),
            'current_plan' => $this->whenLoaded('subscription', fn () => $this->subscription?->plan ? new PlanResource($this->subscription->plan) : null),
            'subscription_status' => $this->whenLoaded('subscription', fn () => $this->subscription?->status),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
