<?php

namespace App\DTOs;

class CompanyData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $businessName = null,
        public readonly ?string $taxId = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $logo = null,
        public readonly ?string $theme = 'light',
        public readonly ?int $subscriptionPlanId = null,
        public readonly ?string $timezone = 'America/Caracas',
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->name,
            businessName: $request->business_name,
            taxId: $request->tax_id,
            email: $request->email,
            phone: $request->phone,
            address: $request->address,
            logo: $request->logo,
            theme: $request->theme ?? 'light',
            subscriptionPlanId: $request->subscription_plan_id,
            timezone: $request->timezone ?? 'America/Caracas',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'business_name' => $this->businessName,
            'tax_id' => $this->taxId,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'logo' => $this->logo,
            'theme' => $this->theme,
            'subscription_plan_id' => $this->subscriptionPlanId,
            'timezone' => $this->timezone,
        ];
    }
}