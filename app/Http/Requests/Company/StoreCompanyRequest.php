<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use App\DTOs\CompanyData;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'business_name' => 'nullable|string|max:200',
            'tax_id' => 'nullable|string|max:50|unique:companies,tax_id',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'logo' => 'nullable|string|max:255',
            'theme' => 'nullable|string|in:light,dark,auto',
            'timezone' => 'nullable|string|max:50',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id,is_active,1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la empresa es requerido',
            'tax_id.unique' => 'El RIF ya está registrado en otra empresa',
            'theme.in' => 'El tema debe ser light, dark o auto',
        ];
    }

    public function toDto(): CompanyData
    {
        return new CompanyData(
            name: $this->name,
            businessName: $this->business_name,
            taxId: $this->tax_id,
            email: $this->email,
            phone: $this->phone,
            address: $this->address,
            logo: $this->logo,
            theme: $this->theme ?? 'light',
            timezone: $this->timezone ?? 'America/Caracas',
            subscriptionPlanId: $this->subscription_plan_id,
        );
    }
}