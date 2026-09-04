<?php

namespace App\Http\Requests\SubscriptionPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:50', 'unique:subscription_plans,slug'],
            'description' => ['nullable', 'string'],
            'max_users' => ['required', 'integer', 'min:0', 'max:999'],
            'max_bank_accounts' => ['required', 'integer', 'min:0', 'max:999'],
            'max_transactions_per_month' => ['required', 'integer', 'min:0', 'max:999999'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del plan es obligatorio',
            'slug.unique' => 'El slug ya está en uso',
            'max_users.required' => 'El límite de usuarios es obligatorio',
            'max_bank_accounts.required' => 'El límite de cuentas bancarias es obligatorio',
            'max_transactions_per_month.required' => 'El límite de transacciones mensuales es obligatorio',
            'price.required' => 'El precio es obligatorio',
            'currency_id.exists' => 'La moneda seleccionada no existe',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Si no se proporciona slug, generarlo automáticamente
        if (empty($this->slug) && !empty($this->name)) {
            $this->merge([
                'slug' => \Str::slug($this->name),
            ]);
        }
    }
}