<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:50',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'business_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'business_phone' => 'nullable|string|max:50',
            'business_email' => 'nullable|email|max:255',
            'sync_to_map' => 'nullable|boolean',
        ];
    }
}
