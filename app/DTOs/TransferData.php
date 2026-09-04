<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class TransferData
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $userId,
        public readonly int $fromAccountId,
        public readonly int $toAccountId,
        public readonly float $amount,
        public readonly int $currencyId,
        public readonly string $date,
        public readonly string $description,
        public readonly ?string $reference = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            companyId: $request->user()->current_company_id,
            userId: $request->user()->id,
            fromAccountId: (int) $request->from_account_id,
            toAccountId: (int) $request->to_account_id,
            amount: (float) $request->amount,
            currencyId: (int) $request->currency_id,
            date: $request->date,
            description: $request->description,
            reference: $request->reference,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'from_account_id' => $this->fromAccountId,
            'to_account_id' => $this->toAccountId,
            'amount' => $this->amount,
            'currency_id' => $this->currencyId,
            'date' => $this->date,
            'description' => $this->description,
            'reference' => $this->reference,
        ];
    }
}