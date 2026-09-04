<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $user = auth()->user();
        $companyIds = $user->companies()->pluck('companies.id')->toArray();

        return [
            'company_id' => [
                'required',
                'integer',
                Rule::in($companyIds),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'El ID de la empresa es requerido',
            'company_id.in' => 'No tienes acceso a esta empresa',
        ];
    }
}