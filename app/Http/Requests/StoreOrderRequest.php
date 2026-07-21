<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'supplier_name' => 'nullable|string|max:255',
            'subtotal' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3|in:COP,USD,EUR',
            'invoice_image_url' => 'nullable|url|max:2048',
            'ocr_raw_text' => 'nullable|string',
            'ocr_confidence' => 'nullable|numeric|between:0,1',
            'status' => 'nullable|in:pending,processed,verified,error',
            'notes' => 'nullable|string|max:1000',
            'items' => 'nullable|array|min:1',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.matched_product_id' => 'nullable|exists:store_products,id',
        ];
    }
}
