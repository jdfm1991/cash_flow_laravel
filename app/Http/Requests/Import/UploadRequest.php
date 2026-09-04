<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'bank_id' => 'required|exists:banks,id,is_active,1',
            'bank_account_id' => 'nullable|exists:bank_accounts,id,is_active,1',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es requerido',
            'file.file' => 'El archivo no es válido',
            'file.mimes' => 'El formato debe ser XLSX, XLS o CSV',
            'file.max' => 'El archivo no debe exceder los 5MB',
            'bank_id.required' => 'El banco es requerido',
            'bank_id.exists' => 'El banco no existe o está inactivo',
            'bank_account_id.exists' => 'La cuenta bancaria no existe o está inactiva',
        ];
    }
}