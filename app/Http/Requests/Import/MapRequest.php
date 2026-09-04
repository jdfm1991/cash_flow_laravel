<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class MapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|string',
            'mappings' => 'required|array|min:1',
            'mappings.*.transaction_id' => 'required|integer',
            'mappings.*.account_id' => 'required|exists:accounts,id,is_active,1',
        ];
    }

    public function messages(): array
    {
        return [
            'session_id.required' => 'El ID de sesión es requerido',
            'mappings.required' => 'Los mapeos son requeridos',
            'mappings.min' => 'Debe proporcionar al menos un mapeo',
            'mappings.*.transaction_id.required' => 'El ID de transacción es requerido',
            'mappings.*.account_id.required' => 'La cuenta es requerida',
            'mappings.*.account_id.exists' => 'La cuenta no existe o está inactiva',
        ];
    }
}