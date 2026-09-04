<?php

namespace App\DTOs;

class ReportData
{
    public function __construct(
        public readonly array $rows,
        public readonly array $totals,
        public readonly string $baseCurrency,
        public readonly string $defaultCurrency,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $type = null,
    ) {}

    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'totals' => $this->totals,
            'base_currency' => $this->baseCurrency,
            'default_currency' => $this->defaultCurrency,
            'date_range' => [
                'start' => $this->startDate,
                'end' => $this->endDate,
            ],
            'type' => $this->type,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}