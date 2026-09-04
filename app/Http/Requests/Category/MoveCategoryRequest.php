<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class MoveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|exists:categories,id,is_active,1',
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'La categoría padre no existe o está inactiva',
        ];
    }
}