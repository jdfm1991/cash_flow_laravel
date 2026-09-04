<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ✅ Delegar al controlador
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id,is_active,1',
            'name' => 'required|string|max:100|unique:accounts,name',
            'description' => 'nullable|string',
            'codigo_contable' => 'nullable|string|max:20',
            'cuenta_contable_id' => 'nullable|integer',
            'is_system' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'La categoría es requerida',
            'category_id.exists' => 'La categoría no existe o está inactiva',
            'name.required' => 'El nombre de la cuenta es requerido',
            'name.unique' => 'Ya existe una cuenta con este nombre',
            'codigo_contable.max' => 'El código contable no puede exceder 20 caracteres',
        ];
    }
}