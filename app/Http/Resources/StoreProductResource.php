<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'sku' => $this->sku,
            'sale_price' => (float) $this->sale_price,
            'purchase_price' => $this->purchase_price ? (float) $this->purchase_price : null,
            'stock_quantity' => $this->stock_quantity,
            'min_stock' => $this->min_stock,
            'unit' => $this->unit,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'is_visible_on_map' => $this->is_visible_on_map,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
