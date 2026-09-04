<?php

namespace App\DTOs;

class BankAccountData
{
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?int $bankId = null,        // ✅ Ahora es nullable
        public readonly ?int $currencyId = null,    // ✅ Ahora es nullable
        public readonly ?string $accountNumber = null,
        public readonly ?string $alias = null,
        public readonly ?string $accountType = null,
        public readonly ?string $accountHolder = null,
        public readonly ?float $openingBalance = null,
        public readonly ?string $openedAt = null,
        public readonly ?string $notes = null,
        public readonly ?bool $isActive = null,
        public readonly ?bool $isDefault = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'bank_id' => $this->bankId,
            'currency_id' => $this->currencyId,
            'account_number' => $this->accountNumber,
            'alias' => $this->alias,
            'account_type' => $this->accountType,
            'account_holder' => $this->accountHolder,
            'opening_balance' => $this->openingBalance,
            'opened_at' => $this->openedAt,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
            'is_default' => $this->isDefault,
        ], fn($value) => $value !== null);
    }
}