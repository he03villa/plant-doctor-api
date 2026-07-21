<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date?->toISOString(),
            'supplier_name' => $this->supplier_name,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'invoice_image_url' => $this->invoice_image_url,
            'status' => $this->status,
            'notes' => $this->notes,
            'ocr_confidence' => $this->ocr_confidence ? (float) $this->ocr_confidence : null,
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
            ],
            'items' => $this->items->map(fn($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'matched_product' => $item->matchedProduct ? [
                    'id' => $item->matchedProduct->id,
                    'name' => $item->matchedProduct->name,
                    'sale_price' => (float) $item->matchedProduct->sale_price,
                ] : null,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
