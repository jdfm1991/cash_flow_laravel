<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ✅ Usar email como identificador principal
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'full_name' => 'required|string|max:100',
            'company_name' => 'required|string|max:100',
            'business_name' => 'nullable|string|max:200',
            'tax_id' => 'nullable|string|max:50|unique:companies,tax_id',
            'company_email' => 'nullable|email|max:100',
            'company_phone' => 'nullable|string|max:20',
            'company_address' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El email es requerido',
            'email.unique' => 'El email ya está registrado',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'full_name.required' => 'El nombre completo es requerido',
            'company_name.required' => 'El nombre de la empresa es requerido',
            'tax_id.unique' => 'El RIF ya está registrado en otra empresa',
        ];
    }
}