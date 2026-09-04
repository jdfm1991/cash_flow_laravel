<?php

namespace App\Http\Requests\SubscriptionPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:50'],
            'slug' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('subscription_plans', 'slug')->ignore($id),
            ],
            'description' => ['nullable', 'string'],
            'max_users' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'max_bank_accounts' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'max_transactions_per_month' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'El slug ya está en uso',
            'currency_id.exists' => 'La moneda seleccionada no existe',
        ];
    }
}