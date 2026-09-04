<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * ✅ Delegar la autorización al controlador
     */
    public function authorize(): bool
    {
        // ✅ Devolver true para que la validación pase
        // La autorización se maneja en el controlador
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:banks,name',
            'code' => 'nullable|string|max:20|unique:banks,code',
            'country_code' => 'nullable|string|size:2',
            'website' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:20',
            'logo_path' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del banco es requerido',
            'name.unique' => 'Ya existe un banco con este nombre',
            'code.unique' => 'Ya existe un banco con este código',
            'country_code.size' => 'El código de país debe tener 2 caracteres',
            'website.url' => 'El sitio web debe ser una URL válida',
        ];
    }
}