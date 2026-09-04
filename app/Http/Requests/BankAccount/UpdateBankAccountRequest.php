<?php

namespace App\Http\Requests\BankAccount;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\AccountType;
use App\DTOs\BankAccountData;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => 'sometimes|exists:banks,id,is_active,1',
            'currency_id' => 'sometimes|exists:currencies,id,is_active,1',
            'account_number' => 'sometimes|string|max:50',
            'alias' => 'sometimes|string|max:100',
            'account_type' => 'sometimes|in:' . implode(',', AccountType::values()),
            'account_holder' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:0',
            'opened_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];
    }

    /**
     * Convertir a DTO para actualización
     */
    public function toDto(): BankAccountData
    {
        return new BankAccountData(
            companyId: null, // No se usa en update
            bankId: $this->bank_id ? (int) $this->bank_id : null,
            currencyId: $this->currency_id ? (int) $this->currency_id : null,
            accountNumber: $this->account_number,
            alias: $this->alias,
            accountType: $this->account_type,
            accountHolder: $this->account_holder,
            openingBalance: $this->opening_balance !== null ? (float) $this->opening_balance : null,
            openedAt: $this->opened_at,
            notes: $this->notes,
            isActive: $this->is_active !== null ? (bool) $this->is_active : null,
            isDefault: $this->is_default !== null ? (bool) $this->is_default : null,
        );
    }
}