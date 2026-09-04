<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'logo' => 'required|image|mimes:png,jpg,jpeg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'El logo es requerido',
            'logo.image' => 'El archivo debe ser una imagen',
            'logo.mimes' => 'El formato debe ser PNG, JPG, JPEG o GIF',
            'logo.max' => 'El logo no debe exceder los 2MB',
        ];
    }
}