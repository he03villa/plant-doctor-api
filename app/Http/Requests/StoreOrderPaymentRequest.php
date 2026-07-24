<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'payment_method' => 'required|in:cash,card,transfer',
            'payment_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'receipt_image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:10240',
        ];
    }
}
