<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $user = auth()->user();

        return [
            'full_name' => 'sometimes|string|max:100',
            'username' => 'sometimes|string|max:50|unique:users,username,' . $user->id,
            'email' => 'sometimes|email|max:100|unique:users,email,' . $user->id,
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.max' => 'El nombre completo no puede exceder 100 caracteres',
            'username.unique' => 'El nombre de usuario ya está en uso',
            'email.unique' => 'El email ya está registrado por otro usuario',
        ];
    }
}