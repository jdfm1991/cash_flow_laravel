<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('id');
        $currentUser = auth()->user();

        // Solo super_admin puede actualizar a otros usuarios de otras empresas
        if ($user && $user->id !== $currentUser->id) {
            return $currentUser->hasRole('super_admin');
        }

        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'username' => 'sometimes|string|max:50|unique:users,username,' . $id,
            'email' => 'sometimes|email|max:100|unique:users,email,' . $id,
            'full_name' => 'sometimes|string|max:100',
            'password' => 'nullable|string|min:8|confirmed',
            'avatar' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'email_verified' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'El nombre de usuario ya está en uso',
            'email.unique' => 'El email ya está registrado',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'roles.*.exists' => 'Uno de los roles no existe',
        ];
    }
}