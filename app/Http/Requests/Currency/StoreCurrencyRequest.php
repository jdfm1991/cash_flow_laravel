<?php

namespace App\Http\Requests\Currency;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ✅ Delegar al controlador
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:50',
            'symbol' => 'required|string|max:5',
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'is_base' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de la moneda es requerido',
            'code.size' => 'El código debe tener 3 caracteres',
            'code.unique' => 'El código de moneda ya existe',
            'name.required' => 'El nombre de la moneda es requerido',
            'symbol.required' => 'El símbolo de la moneda es requerido',
            'decimal_places.integer' => 'El número de decimales debe ser un entero',
            'decimal_places.min' => 'El número de decimales no puede ser negativo',
            'decimal_places.max' => 'El número de decimales no puede ser mayor a 4',
        ];
    }
}