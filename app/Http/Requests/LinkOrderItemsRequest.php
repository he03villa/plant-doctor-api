<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'integer', 'exists:store_products,id'],
        ];
    }
}
