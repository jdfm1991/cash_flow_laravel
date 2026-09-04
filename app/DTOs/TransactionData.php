<?php

namespace App\DTOs;

use App\Enums\TransactionType;
use App\Enums\PaymentMethod;
use Illuminate\Http\Request;

class TransactionData
{
    public function __construct(
        public readonly ?int $companyId = null,    // ✅ Ahora opcional
        public readonly ?int $userId = null,       // ✅ Ahora opcional
        public readonly ?string $type = null,      // ✅ Ahora opcional
        public readonly ?float $amount = null,
        public readonly ?int $currencyId = null,
        public readonly ?string $date = null,
        public readonly ?string $description = null,
        public readonly ?int $accountId = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $bankAccountId = null,
        public readonly ?string $reference = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $receiptPath = null,
        public readonly ?string $exchangeRate = null,
        public readonly ?string $amountConverted = null
    ) {}

    public static function fromRequest(Request $request): self
    {
        $user = auth()->user();

        // ✅ Convertir 0 a NULL
        $bankAccountId = $request->bank_account_id;
        if ($bankAccountId == 0 || $bankAccountId === '0') {
            $bankAccountId = null;
        }

        return new self(
            companyId: $user->current_company_id ?? null,
            userId: $user->id ?? null,
            type: $request->type,
            amount: $request->amount ? (float) $request->amount : null,
            currencyId: $request->currency_id ? (int) $request->currency_id : null,
            date: $request->date ?? now()->toDateString(),
            description: $request->description,
            accountId: $request->account_id ? (int) $request->account_id : null,
            categoryId: $request->category_id ? (int) $request->category_id : null,
            bankAccountId: $bankAccountId ? (int) $bankAccountId : null,
            reference: $request->reference,
            paymentMethod: $request->payment_method ?? 'bank',
            receiptPath: $request->receipt_path,
            exchangeRate: $request->exchange_rate,
            amountConverted: $request->amount_converted
        );
    }

    /**
     * Crear DTO para CREATE (requiere todos los campos)
     */
    public static function forCreate(Request $request): self
    {
        $user = auth()->user();

        // ✅ Convertir 0 a NULL
        $bankAccountId = $request->bank_account_id;
        if ($bankAccountId == 0 || $bankAccountId === '0') {
            $bankAccountId = null;
        }

        return new self(
            companyId: $user->current_company_id,
            userId: $user->id,
            type: $request->type,
            amount: (float) $request->amount,
            currencyId: (int) $request->currency_id,
            date: $request->date ?? now()->toDateString(),
            description: $request->description,
            accountId: (int) $request->account_id,
            categoryId: (int) $request->category_id,
            bankAccountId: $bankAccountId ? (int) $bankAccountId : null,
            reference: $request->reference,
            paymentMethod: $request->payment_method ?? 'bank',
            receiptPath: $request->receipt_path,
            exchangeRate: $request->exchange_rate,
            amountConverted: $request->amount_converted
        );
    }

    public function isValidType(): bool
    {
        if (!$this->type) {
            return false;
        }
        return in_array($this->type, TransactionType::values());
    }

    public function isValidPaymentMethod(): bool
    {
        if (!$this->paymentMethod) {
            return true;
        }
        return in_array($this->paymentMethod, PaymentMethod::values());
    }

    public function isTransfer(): bool
    {
        return $this->type === TransactionType::TRANSFER->value;
    }

    public function isIncome(): bool
    {
        return $this->type === TransactionType::INCOME->value;
    }

    public function isExpense(): bool
    {
        return $this->type === TransactionType::EXPENSE->value;
    }
}