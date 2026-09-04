<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ✅ Devolver true, la autorización se maneja en el controlador
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => 'sometimes|string|max:100|unique:banks,name,' . $id,
            'code' => 'nullable|string|max:20|unique:banks,code,' . $id,
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
            'name.unique' => 'Ya existe un banco con este nombre',
            'code.unique' => 'Ya existe un banco con este código',
            'country_code.size' => 'El código de país debe tener 2 caracteres',
            'website.url' => 'El sitio web debe ser una URL válida',
        ];
    }
}