<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_name' => 'sometimes|string|max:100',
            'app_locale' => 'sometimes|string|in:es,en,pt',
            'app_timezone' => 'sometimes|string|max:50',
            'date_format' => 'sometimes|string|max:20',
            'time_format' => 'sometimes|string|max:10',
            'items_per_page' => 'sometimes|integer|min:5|max:100',
            'maintenance_mode' => 'nullable|boolean',
            'allow_registration' => 'nullable|boolean',
            'allow_public_api' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'app_name.max' => 'El nombre de la aplicación no puede exceder 100 caracteres',
            'app_locale.in' => 'El idioma no es válido',
            'items_per_page.min' => 'El número de items por página debe ser al menos 5',
            'items_per_page.max' => 'El número de items por página no puede exceder 100',
        ];
    }
}