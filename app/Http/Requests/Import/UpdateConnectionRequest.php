<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:100',
            'type' => 'nullable|in:migration,replication,integration',
            'host' => 'sometimes|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'db_name' => 'sometimes|string|max:100',
            'username' => 'sometimes|string|max:100',
            'password' => 'nullable|string|min:1',
            'table_name' => 'sometimes|string|max:100',
            'field_mapping' => 'nullable|array',
            'query_template' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'port.integer' => 'El puerto debe ser un número entero',
            'port.min' => 'El puerto debe ser mayor a 0',
            'port.max' => 'El puerto debe ser menor a 65536',
            'type.in' => 'El tipo de conexión no es válido',
        ];
    }
}