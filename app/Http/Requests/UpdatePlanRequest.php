<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan');

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:plans,slug,'.$planId,
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'features' => 'sometimes|array',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
