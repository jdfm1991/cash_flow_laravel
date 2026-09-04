<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use App\DTOs\CompanyData;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('id');
        $user = auth()->user();

        return $user->hasRole('super_admin') ||
            $user->isCompanyOwner($company);
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:100',
            'business_name' => 'nullable|string|max:200',
            'tax_id' => 'nullable|string|max:50|unique:companies,tax_id,' . $this->route('id'),
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'logo' => 'nullable|string|max:255',
            'theme' => 'nullable|string|in:light,dark,auto',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
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
            theme: $this->theme,
            timezone: $this->timezone,
            isActive: $this->is_active,
        );
    }
}