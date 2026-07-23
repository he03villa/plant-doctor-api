<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price_monthly' => (float) $this->price_monthly,
            'price_yearly' => $this->price_yearly ? (float) $this->price_yearly : null,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
        ];
    }
}
