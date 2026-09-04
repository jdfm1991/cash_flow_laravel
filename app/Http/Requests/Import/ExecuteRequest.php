<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'connection_id' => 'required|exists:external_connections,id,is_active,1',
            'session_id' => 'required|string',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
            'mappings' => 'nullable|array',
            'mappings.*.transaction_id' => 'required_with:mappings|integer',
            'mappings.*.account_id' => 'required_with:mappings|exists:accounts,id,is_active,1',
            'type' => 'nullable|in:income,expense,all',
        ];
    }

    public function messages(): array
    {
        return [
            'connection_id.required' => 'La conexión es requerida',
            'connection_id.exists' => 'La conexión no existe o está inactiva',
            'session_id.required' => 'El ID de sesión es requerido',
            'year.required' => 'El año es requerido',
            'month.required' => 'El mes es requerido',
            'mappings.*.transaction_id.required_with' => 'El ID de transacción es requerido para cada mapeo',
            'mappings.*.account_id.required_with' => 'La cuenta es requerida para cada mapeo',
            'mappings.*.account_id.exists' => 'La cuenta no existe o está inactiva',
            'type.in' => 'El tipo no es válido',
        ];
    }
}