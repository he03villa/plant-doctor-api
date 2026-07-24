<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ocr_text' => 'required|string|min:10',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:10240',
        ];
    }
}
