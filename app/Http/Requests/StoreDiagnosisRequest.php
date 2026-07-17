<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plant_id' => 'nullable|exists:plants,id',
            'image' => 'required|image|max:10240',
            'organ' => 'nullable|string|in:leaf,flower,fruit,bark',
            'notes' => 'nullable|string',
        ];
    }
}
