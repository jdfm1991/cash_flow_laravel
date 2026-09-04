<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class ExchangeRateData
{
    public function __construct(
        public readonly int $fromCurrencyId,
        public readonly int $toCurrencyId,
        public readonly float $rate,
        public readonly string $effectiveDate,
        public readonly ?string $source = 'manual',
        public readonly ?string $notes = null,
        public readonly ?int $createdBy = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            fromCurrencyId: (int) $request->from_currency_id,
            toCurrencyId: (int) $request->to_currency_id,
            rate: (float) $request->rate,
            effectiveDate: $request->effective_date,
            source: $request->source ?? 'manual',
            notes: $request->notes,
            createdBy: $request->user()?->id,
        );
    }

    public function toArray(): array
    {
        return [
            'from_currency_id' => $this->fromCurrencyId,
            'to_currency_id' => $this->toCurrencyId,
            'rate' => $this->rate,
            'effective_date' => $this->effectiveDate,
            'source' => $this->source,
            'notes' => $this->notes,
            'created_by' => $this->createdBy,
        ];
    }
}