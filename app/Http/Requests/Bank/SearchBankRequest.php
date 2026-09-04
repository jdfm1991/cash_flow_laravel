<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;

class SearchBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Búsqueda pública
    }

    public function rules(): array
    {
        return [
            'q' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'El término de búsqueda es requerido',
            'q.min' => 'El término de búsqueda debe tener al menos 2 caracteres',
            'limit.max' => 'El límite máximo es 50 resultados',
        ];
    }
}