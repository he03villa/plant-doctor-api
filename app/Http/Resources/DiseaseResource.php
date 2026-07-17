<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiseaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scientific_name' => $this->scientific_name,
            'description' => $this->description,
            'symptoms' => $this->symptoms,
            'treatment' => $this->treatment,
            'prevention' => $this->prevention,
            'image_url' => $this->image_path
                ? (str_starts_with($this->image_path, 'http') ? $this->image_path : asset('storage/' . $this->image_path))
                : null,
            'category' => $this->category,
            'severity' => $this->severity,
            'is_active' => $this->is_active,
            'diagnoses_count' => $this->whenCounted('diagnoses'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
