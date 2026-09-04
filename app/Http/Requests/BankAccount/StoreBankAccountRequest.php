<?php

namespace App\Http\Requests\BankAccount;

use Illuminate\Foundation\Http\FormRequest;
use App\DTOs\BankAccountData;
use App\Enums\AccountType;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => 'required|exists:banks,id,is_active,1',
            'currency_id' => 'required|exists:currencies,id,is_active,1',
            'account_number' => 'required|string|max:50',
            'alias' => 'required|string|max:100',
            'account_type' => 'required|in:' . implode(',', AccountType::values()),
            'account_holder' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:0',
            'opened_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'bank_id.required' => 'El banco es requerido',
            'bank_id.exists' => 'El banco no existe o está inactivo',
            'currency_id.required' => 'La moneda es requerida',
            'currency_id.exists' => 'La moneda no existe o está inactiva',
            'account_number.required' => 'El número de cuenta es requerido',
            'alias.required' => 'El alias es requerido',
            'account_type.required' => 'El tipo de cuenta es requerido',
            'account_type.in' => 'El tipo de cuenta no es válido',
            'opening_balance.min' => 'El saldo inicial no puede ser negativo',
        ];
    }

    /**
     * Convertir a DTO para creación
     */
    public function toDto(int $companyId): BankAccountData
    {
        return new BankAccountData(
            companyId: $companyId,
            bankId: (int) $this->bank_id,
            currencyId: (int) $this->currency_id,
            accountNumber: $this->account_number,
            alias: $this->alias,
            accountType: $this->account_type,
            accountHolder: $this->account_holder,
            openingBalance: $this->opening_balance !== null ? (float) $this->opening_balance : 0,
            openedAt: $this->opened_at,
            notes: $this->notes,
            isActive: $this->is_active ?? true,
            isDefault: $this->is_default ?? false,
        );
    }
}