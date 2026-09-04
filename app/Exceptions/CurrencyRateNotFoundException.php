<?php

namespace App\Exceptions;

use Exception;

class CurrencyRateNotFoundException extends Exception
{
    protected int $fromCurrencyId;
    protected int $toCurrencyId;
    protected string $date;

    public function __construct(string $message, int $fromCurrencyId, int $toCurrencyId, string $date)
    {
        $this->fromCurrencyId = $fromCurrencyId;
        $this->toCurrencyId = $toCurrencyId;
        $this->date = $date;

        parent::__construct($message, 404);
    }

    public static function forPair(int $fromId, int $toId, string $date): self
    {
        return new self(
            "No se encontró tasa de cambio para {$fromId} → {$toId} en fecha {$date}",
            $fromId,
            $toId,
            $date
        );
    }

    public function getFromCurrencyId(): int
    {
        return $this->fromCurrencyId;
    }

    public function getToCurrencyId(): int
    {
        return $this->toCurrencyId;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'from_currency_id' => $this->fromCurrencyId,
                'to_currency_id' => $this->toCurrencyId,
                'date' => $this->date,
            ],
        ], 404);
    }
}