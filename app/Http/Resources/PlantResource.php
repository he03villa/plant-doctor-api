<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'species' => $this->species,
            'description' => $this->description,
            'image_url' => $this->image_path
                ? (str_starts_with($this->image_path, 'http') ? $this->image_path : asset('storage/' . $this->image_path))
                : null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'country_name' => $this->country_name,
            'state_name' => $this->state_name,
            'city_name' => $this->city_name,
            'status' => $this->status,
            'diagnoses_count' => $this->whenCounted('diagnoses'),
            'diagnoses' => DiagnosisResource::collection($this->whenLoaded('diagnoses')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
