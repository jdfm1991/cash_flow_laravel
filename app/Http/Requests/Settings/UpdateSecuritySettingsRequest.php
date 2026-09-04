<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_timeout' => 'sometimes|integer|min:5|max:1440',
            'max_login_attempts' => 'sometimes|integer|min:1|max:20',
            'lockout_duration' => 'sometimes|integer|min:1|max:60',
            'password_min_length' => 'sometimes|integer|min:6|max:20',
            'password_requires_uppercase' => 'nullable|boolean',
            'password_requires_numbers' => 'nullable|boolean',
            'password_requires_symbols' => 'nullable|boolean',
            'two_factor_enabled' => 'nullable|boolean',
            'session_per_user' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'session_timeout.min' => 'El tiempo de sesión debe ser al menos 5 minutos',
            'session_timeout.max' => 'El tiempo de sesión no puede exceder 1440 minutos (24 horas)',
            'max_login_attempts.min' => 'Debe permitir al menos 1 intento',
            'max_login_attempts.max' => 'No puede permitir más de 20 intentos',
            'lockout_duration.min' => 'La duración del bloqueo debe ser al menos 1 minuto',
            'lockout_duration.max' => 'La duración del bloqueo no puede exceder 60 minutos',
            'password_min_length.min' => 'La longitud mínima de la contraseña debe ser al menos 6',
            'password_min_length.max' => 'La longitud mínima de la contraseña no puede exceder 20',
        ];
    }
}