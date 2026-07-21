<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'category' => 'nullable|string|in:planta,fertilizante,maceta,sustrato,herramienta,pesticida,otro',
            'sku' => 'nullable|string|max:100',
            'purchase_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'image_url' => 'nullable|url|max:2048',
            'is_visible_on_map' => 'nullable|boolean',
        ];
    }
}
