<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiagnosisResource extends JsonResource
{
    public ?array $nearby_stores = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'confidence_score' => $this->confidence_score,
            'notes' => $this->notes,
            'image_url' => $this->image_url,
            'status' => $this->status,
            'ai_provider' => $this->ai_provider,
            'species' => [
                'name' => $this->species_name,
                'common_names' => $this->species_common_names,
            ],
            'disease' => [
                'name' => $this->disease_name_detected,
                'scientific_name' => $this->disease_name_scientific,
                'severity' => $this->disease_severity,
                'symptoms' => $this->symptoms_observed,
                'treatment' => $this->treatment_recommendation,
                'prevention' => $this->prevention_recommendation,
                'catalog_match' => new DiseaseResource($this->whenLoaded('disease')),
            ],
            'expert_verified' => $this->expert_verified,
            'expert_notes' => $this->expert_notes,
            'plant' => new PlantResource($this->whenLoaded('plant')),
            'expert' => new UserResource($this->whenLoaded('expert')),
            'nearby_stores' => $this->nearby_stores,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
