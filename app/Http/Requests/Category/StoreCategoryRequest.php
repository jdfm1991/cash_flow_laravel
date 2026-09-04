<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\CategoryType;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ✅ Delegar al controlador
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'type' => 'required|in:' . implode(',', CategoryType::values()),
            'parent_id' => 'nullable|exists:categories,id,is_active,1',
            'code' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[a-fA-F0-9]{6}$/',
            'description' => 'nullable|string',
            'is_system' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es requerido',
            'type.required' => 'El tipo de categoría es requerido',
            'type.in' => 'El tipo de categoría no es válido',
            'parent_id.exists' => 'La categoría padre no existe o está inactiva',
            'color.regex' => 'El color debe ser un código hexadecimal válido (#RRGGBB)',
        ];
    }
}