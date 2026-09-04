<?php

namespace App\Http\Requests\Currency;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ✅ Delegar al controlador
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'code' => 'sometimes|string|size:3|unique:currencies,code,' . $id,
            'name' => 'sometimes|string|max:50',
            'symbol' => 'sometimes|string|max:5',
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'is_base' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.size' => 'El código debe tener 3 caracteres',
            'code.unique' => 'El código de moneda ya existe',
            'decimal_places.integer' => 'El número de decimales debe ser un entero',
            'decimal_places.min' => 'El número de decimales no puede ser negativo',
            'decimal_places.max' => 'El número de decimales no puede ser mayor a 4',
        ];
    }
}