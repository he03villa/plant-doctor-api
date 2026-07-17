<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'sub_category' => $this->sub_category,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),
            'publishable_type' => $this->publishable_type,
            'publishable_id' => $this->publishable_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'publishable' => $this->whenLoaded('publishable'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
