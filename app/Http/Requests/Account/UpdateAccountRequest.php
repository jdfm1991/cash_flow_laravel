<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
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
            'category_id' => 'sometimes|exists:categories,id,is_active,1',
            'name' => 'sometimes|string|max:100|unique:accounts,name,' . $id,
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
            'category_id.exists' => 'La categoría no existe o está inactiva',
            'name.unique' => 'Ya existe una cuenta con este nombre',
            'codigo_contable.max' => 'El código contable no puede exceder 20 caracteres',
        ];
    }
}