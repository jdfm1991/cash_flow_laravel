<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class StoreConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'type' => 'nullable|in:migration,replication,integration',
            'host' => 'required|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'db_name' => 'required|string|max:100',
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:1',
            'table_name' => 'required|string|max:100',
            'field_mapping' => 'nullable|array',
            'query_template' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la conexión es requerido',
            'host.required' => 'El host de la base de datos es requerido',
            'db_name.required' => 'El nombre de la base de datos es requerido',
            'username.required' => 'El usuario de la base de datos es requerido',
            'password.required' => 'La contraseña de la base de datos es requerida',
            'table_name.required' => 'El nombre de la tabla es requerido',
            'port.integer' => 'El puerto debe ser un número entero',
            'port.min' => 'El puerto debe ser mayor a 0',
            'port.max' => 'El puerto debe ser menor a 65536',
            'type.in' => 'El tipo de conexión no es válido',
        ];
    }
}