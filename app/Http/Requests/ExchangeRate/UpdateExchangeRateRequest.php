<?php

namespace App\Http\Requests\ExchangeRate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ✅ Delegar al controlador
        return true;
    }

    public function rules(): array
    {
        return [
            'rate' => 'sometimes|numeric|min:0.00000001',
            'source' => 'nullable|in:manual,api,system',
            'notes' => 'nullable|string',
            'is_current' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'rate.min' => 'La tasa de cambio debe ser mayor a 0',
            'source.in' => 'La fuente no es válida',
        ];
    }
}