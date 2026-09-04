<?php

namespace App\DTOs;

class ReportRow
{
    public function __construct(
        public readonly string $category,
        public readonly float $amountInBase,
        public readonly float $amountInDefault,
        public readonly ?float $rate = null,
        public readonly ?string $currencyCode = null,
    ) {}

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'amount_in_base' => $this->amountInBase,
            'amount_in_default' => $this->amountInDefault,
            'rate' => $this->rate,
            'currency_code' => $this->currencyCode,
        ];
    }
}