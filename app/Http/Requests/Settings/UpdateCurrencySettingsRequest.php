<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base_currency_id' => 'sometimes|exists:currencies,id,is_active,1',
            'default_currency_id' => 'sometimes|exists:currencies,id,is_active,1|different:base_currency_id',
            'decimal_places' => 'sometimes|integer|min:0|max:4',
            'currency_display' => 'sometimes|string|in:symbol,code,both',
        ];
    }

    public function messages(): array
    {
        return [
            'base_currency_id.exists' => 'La moneda base no existe o está inactiva',
            'default_currency_id.exists' => 'La moneda por defecto no existe o está inactiva',
            'default_currency_id.different' => 'La moneda base y por defecto deben ser diferentes',
            'decimal_places.min' => 'El número de decimales no puede ser negativo',
            'decimal_places.max' => 'El número de decimales no puede ser mayor a 4',
            'currency_display.in' => 'El formato de visualización no es válido',
        ];
    }
}