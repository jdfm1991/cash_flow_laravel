<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:100|unique:users,email',
            'name' => 'required|string|max:100',
            'password' => 'required|string|min:8|confirmed',
            'avatar' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'email_verified' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
            'companies' => 'nullable|array',
            'companies.*.id' => 'required|exists:companies,id,is_active,1',
            'companies.*.is_default' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El email es requerido',
            'email.unique' => 'El email ya está registrado',
            'full_name.required' => 'El nombre completo es requerido',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'roles.*.exists' => 'Uno de los roles no existe',
            'companies.*.id.required' => 'El ID de la empresa es requerido',
            'companies.*.id.exists' => 'La empresa no existe o está inactiva',
        ];
    }
}