<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'La imagen es requerida',
            'avatar.image' => 'El archivo debe ser una imagen',
            'avatar.mimes' => 'El formato debe ser JPEG, PNG, JPG, GIF o WEBP',
            'avatar.max' => 'La imagen no debe exceder los 2MB',
        ];
    }
}