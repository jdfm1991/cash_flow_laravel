<?php

namespace App\Http\Requests\ExchangeRate;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ✅ Delegar al controlador
        return true;
    }

    public function rules(): array
    {
        return [
            'from_currency_id' => 'required|exists:currencies,id,is_active,1',
            'to_currency_id' => 'required|exists:currencies,id,is_active,1|different:from_currency_id',
            'rate' => 'required|numeric|min:0.00000001',
            'effective_date' => 'required|date|before_or_equal:today',
            'source' => 'nullable|in:manual,api,system',
            'notes' => 'nullable|string',
            'is_current' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'from_currency_id.required' => 'La moneda origen es requerida',
            'to_currency_id.required' => 'La moneda destino es requerida',
            'to_currency_id.different' => 'Las monedas deben ser diferentes',
            'rate.required' => 'La tasa de cambio es requerida',
            'rate.min' => 'La tasa de cambio debe ser mayor a 0',
            'effective_date.required' => 'La fecha efectiva es requerida',
            'effective_date.before_or_equal' => 'La fecha no puede ser futura',
            'source.in' => 'La fuente no es válida',
        ];
    }
}