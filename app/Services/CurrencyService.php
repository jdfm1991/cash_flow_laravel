<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\DTOs\ExchangeRateData;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\CurrencyRateNotFoundException;

class CurrencyService
{
    protected string $cachePrefix = 'currency_rate_';
    protected int $cacheTtl = 3600;

    /**
     * Obtener la moneda base del sistema
     */
    public function getBaseCurrency(): ?Currency
    {
        // ✅ Usar Cache::remember con manejo de excepciones
        try {
            $currency = Cache::remember('currency_base', 3600, function () {
                return Currency::where('is_base', true)
                    ->where('is_active', true)
                    ->first();
            });

            // ✅ Verificar que el objeto sea válido
            if ($currency && !$currency instanceof Currency) {
                // Si es un objeto incompleto, obtener directamente de la BD
                return Currency::where('is_base', true)
                    ->where('is_active', true)
                    ->first();
            }

            return $currency;
        } catch (\Exception $e) {
            // Si falla la caché, obtener directamente
            return Currency::where('is_base', true)
                ->where('is_active', true)
                ->first();
        }
    }

    /**
     * Obtener la moneda por defecto
     */
    public function getDefaultCurrency(): ?Currency
    {
        try {
            $currency = Cache::remember('currency_default', 3600, function () {
                $currency = Currency::where('is_default', true)
                    ->where('is_active', true)
                    ->first();

                if (!$currency) {
                    return $this->getBaseCurrency();
                }

                return $currency;
            });

            if ($currency && !$currency instanceof Currency) {
                $currency = Currency::where('is_default', true)
                    ->where('is_active', true)
                    ->first();

                if (!$currency) {
                    return $this->getBaseCurrency();
                }

                return $currency;
            }

            return $currency;
        } catch (\Exception $e) {
            $currency = Currency::where('is_default', true)
                ->where('is_active', true)
                ->first();

            if (!$currency) {
                return $this->getBaseCurrency();
            }

            return $currency;
        }
    }

    /**
     * Obtener la tasa de cambio entre dos monedas
     */
    public function getRate(int $fromCurrencyId, int $toCurrencyId, ?string $date = null): float
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return 1.0;
        }

        $date = $date ?? now()->toDateString();
        $cacheKey = $this->getCacheKey($fromCurrencyId, $toCurrencyId, $date);

        try {
            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($fromCurrencyId, $toCurrencyId, $date) {
                return $this->getRateFromDatabase($fromCurrencyId, $toCurrencyId, $date);
            });
        } catch (\Exception $e) {
            return $this->getRateFromDatabase($fromCurrencyId, $toCurrencyId, $date);
        }
    }

    /**
     * Obtener la tasa con su ID
     */
    public function getRateWithId(int $fromCurrencyId, int $toCurrencyId, ?string $date = null): array
    {
        $date = $date ?? now()->toDateString();

        $rate = ExchangeRate::where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->where('effective_date', '<=', $date)
            ->where('is_current', true)
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($rate) {
            return [
                'rate' => (float) $rate->rate,
                'exchange_rate_id' => $rate->id,
            ];
        }

        // Buscar inversa
        $rate = ExchangeRate::where('from_currency_id', $toCurrencyId)
            ->where('to_currency_id', $fromCurrencyId)
            ->where('effective_date', '<=', $date)
            ->where('is_current', true)
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($rate) {
            return [
                'rate' => 1 / (float) $rate->rate,
                'exchange_rate_id' => $rate->id,
            ];
        }

        throw new CurrencyRateNotFoundException(
            "No se encontró tasa de cambio para {$fromCurrencyId} → {$toCurrencyId} en fecha {$date}",
            $fromCurrencyId,
            $toCurrencyId,
            $date
        );
    }

    /**
     * Convertir un monto
     */
    public function convert(float $amount, int $fromCurrencyId, int $toCurrencyId, ?string $date = null): float
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return $amount;
        }

        $rate = $this->getRate($fromCurrencyId, $toCurrencyId, $date);
        return round($amount * $rate, 4);
    }

    /**
     * Convertir con referencia a la tasa
     */
    public function convertWithReference(float $amount, int $fromCurrencyId, int $toCurrencyId, ?string $date = null): array
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return [
                'amount' => $amount,
                'rate' => 1.0,
                'exchange_rate_id' => null,
            ];
        }

        $rateInfo = $this->getRateWithId($fromCurrencyId, $toCurrencyId, $date);
        $converted = $amount * $rateInfo['rate'];

        return [
            'amount' => round($converted, 4),
            'rate' => $rateInfo['rate'],
            'exchange_rate_id' => $rateInfo['exchange_rate_id'],
        ];
    }

    /**
     * Invalidar caché
     */
    public function invalidateCache(int $fromId, int $toId, ?string $date = null): void
    {
        if ($date) {
            Cache::forget($this->getCacheKey($fromId, $toId, $date));
        } else {
            Cache::forget('currency_base');
            Cache::forget('currency_default');
            Cache::forget('currency_rate_*');
        }
    }

    /**
     * Generar clave de caché
     */
    protected function getCacheKey(int $fromId, int $toId, string $date): string
    {
        return "{$this->cachePrefix}{$fromId}_{$toId}_{$date}";
    }

    /**
     * Buscar tasa en base de datos
     */
    protected function getRateFromDatabase(int $fromId, int $toId, string $date): float
    {
        $rate = ExchangeRate::where('from_currency_id', $fromId)
            ->where('to_currency_id', $toId)
            ->where('effective_date', '<=', $date)
            ->where('is_current', true)
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($rate) {
            return (float) $rate->rate;
        }

        // Buscar inversa
        $rate = ExchangeRate::where('from_currency_id', $toId)
            ->where('to_currency_id', $fromId)
            ->where('effective_date', '<=', $date)
            ->where('is_current', true)
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($rate) {
            return 1 / (float) $rate->rate;
        }

        throw new CurrencyRateNotFoundException(
            "No se encontró tasa de cambio para {$fromId} → {$toId} en fecha {$date}",
            $fromId,
            $toId,
            $date
        );
    }
}