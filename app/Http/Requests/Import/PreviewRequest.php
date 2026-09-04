<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class PreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'connection_id' => 'required|exists:external_connections,id,is_active,1',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
            'bank_id' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'connection_id.required' => 'La conexión es requerida',
            'connection_id.exists' => 'La conexión no existe o está inactiva',
            'year.required' => 'El año es requerido',
            'year.min' => 'El año debe ser mayor o igual a 2000',
            'year.max' => 'El año no puede ser mayor a ' . (date('Y') + 1),
            'month.required' => 'El mes es requerido',
            'month.min' => 'El mes debe ser entre 1 y 12',
            'month.max' => 'El mes debe ser entre 1 y 12',
            'bank_id.required' => 'El banco es requerido',
            'bank_id.min' => 'El banco debe ser un ID válido',
        ];
    }
}