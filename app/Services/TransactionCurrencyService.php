<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Log;

class TransactionCurrencyService
{
    /**
     * ✅ Calcular conversión de moneda para una transacción
     *
     * Reglas:
     * - `amount` SIEMPRE guarda el monto en VES (moneda base)
     * - `amount_converted` SIEMPRE guarda el monto en la moneda "contraria"
     *
     * Caso A: Moneda ingresada = VES (base)
     *   - amount = monto ingresado (VES)
     *   - amount_converted = monto ingresado / tasa (USD)
     *
     * Caso B: Moneda ingresada ≠ VES
     *   - amount = monto ingresado * tasa (VES)
     *   - amount_converted = monto ingresado (moneda original)
     *
     * @param int $currencyId Moneda seleccionada
     * @param float $inputAmount Monto ingresado por el usuario
     * @param string $date Fecha de la transacción
     * @return array
     */
    public function calculateConversion(int $currencyId, float $inputAmount, string $date): array
    {
        $result = [
            'success' => false,
            'amount' => $inputAmount,              // Monto en VES
            'amount_converted' => $inputAmount,    // Monto en la moneda contraria
            'exchange_rate' => 1,
            'exchange_rate_id' => null,
            'currency_from' => null,
            'currency_to' => null,
            'message' => '',
        ];

        try {
            // 1. Obtener moneda base (VES) y por defecto (USD)
            $baseCurrency = Currency::where('is_base', true)->first();
            $defaultCurrency = Currency::where('is_default', true)->first();

            if (!$baseCurrency) {
                $result['message'] = 'No se encontró la moneda base.';
                return $result;
            }

            if (!$defaultCurrency) {
                $defaultCurrency = $baseCurrency;
            }

            // 2. Obtener la moneda seleccionada
            $currency = Currency::find($currencyId);

            if (!$currency) {
                $result['message'] = 'No se encontró la moneda seleccionada.';
                return $result;
            }

            // 3. CASO A: Moneda seleccionada ES la moneda base (VES)
            if ($currency->id === $baseCurrency->id) {
                return $this->handleBaseCurrency(
                    $baseCurrency,
                    $defaultCurrency,
                    $inputAmount,
                    $date
                );
            }

            // 4. CASO B: Moneda seleccionada ≠ VES
            return $this->handleForeignCurrency(
                $currency,
                $baseCurrency,
                $inputAmount,
                $date
            );

        } catch (\Exception $e) {
            Log::error('Error en calculateConversion', [
                'currency_id' => $currencyId,
                'amount' => $inputAmount,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
            $result['message'] = 'Error al calcular la conversión: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * ✅ CASO A: Moneda ingresada = VES (base)
     * - amount = monto ingresado (VES)
     * - amount_converted = monto ingresado / tasa (USD)
     */
    protected function handleBaseCurrency(
        Currency $baseCurrency,
        Currency $defaultCurrency,
        float $inputAmount,
        string $date
    ): array {
        // Si la moneda base y por defecto son iguales, no hay conversión
        if ($baseCurrency->id === $defaultCurrency->id) {
            return [
                'success' => true,
                'amount' => $inputAmount,              // VES
                'amount_converted' => $inputAmount,    // VES
                'exchange_rate' => 1,
                'exchange_rate_id' => null,
                'currency_from' => $baseCurrency->code,
                'currency_to' => $defaultCurrency->code,
                'message' => "Transacción en moneda base ({$baseCurrency->code})",
            ];
        }

        // Buscar tasa USD → VES
        $rate = $this->findExchangeRate(
            $baseCurrency->id,  // from: VES
            $defaultCurrency->id,     // to: USD
            $date
        );

        if (!$rate || $rate->rate <= 0) {
            return [
                'success' => false,
                'amount' => $inputAmount,              // VES
                'amount_converted' => 0,
                'exchange_rate' => 0,
                'exchange_rate_id' => null,
                'currency_from' => $baseCurrency->code,
                'currency_to' => $defaultCurrency->code,
                'message' => "No se encontró tasa de cambio para {$defaultCurrency->code} → {$baseCurrency->code} en la fecha {$date}",
            ];
        }

        // ✅ Convertir: VES / tasa = USD
        $convertedToDefault = $inputAmount / $rate->rate;

        return [
            'success' => true,
            'amount' => $inputAmount,                       // ✅ VES (moneda base)
            'amount_converted' => round($convertedToDefault, 4),  // ✅ USD (moneda por defecto)
            'exchange_rate' => (float) $rate->rate,
            'exchange_rate_id' => $rate->id,
            'currency_from' => $baseCurrency->code,
            'currency_to' => $defaultCurrency->code,
            'message' => "Convertido de {$baseCurrency->code} a {$defaultCurrency->code}",
        ];
    }

    /**
     * ✅ CASO B: Moneda ingresada ≠ VES
     * - amount = monto ingresado * tasa (VES)
     * - amount_converted = monto ingresado (moneda original)
     */
    protected function handleForeignCurrency(
        Currency $currency,
        Currency $baseCurrency,
        float $inputAmount,
        string $date
    ): array {
        // Buscar tasa [moneda] → VES
        $rate = $this->findExchangeRate(
            $baseCurrency->id,      // from: VES 
            $currency->id,  // to: [moneda]
            $date
        );

        if (!$rate || $rate->rate <= 0) {
            return [
                'success' => false,
                'amount' => 0,
                'amount_converted' => $inputAmount,
                'exchange_rate' => 0,
                'exchange_rate_id' => null,
                'currency_from' => $currency->code,
                'currency_to' => $baseCurrency->code,
                'message' => "No se encontró tasa de cambio para {$currency->code} → {$baseCurrency->code} en la fecha {$date}",
            ];
        }

        // ✅ Convertir: amount (VES) = inputAmount * tasa
        $convertedToBase = $inputAmount * $rate->rate;

        return [
            'success' => true,
            'amount' => round($convertedToBase, 4),   // ✅ VES (moneda base)
            'amount_converted' => $inputAmount,        // ✅ Moneda original (USD/EUR)
            'exchange_rate' => (float) $rate->rate,
            'exchange_rate_id' => $rate->id,
            'currency_from' => $currency->code,
            'currency_to' => $baseCurrency->code,
            'message' => "Convertido de {$currency->code} a {$baseCurrency->code}",
        ];
    }

    /**
     * ✅ Buscar la tasa de cambio más cercana a la fecha
     */
    protected function findExchangeRate(int $fromCurrencyId, int $toCurrencyId, string $date): ?ExchangeRate
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return null;
        }

        // Buscar la tasa exacta de esa fecha
        $rate = ExchangeRate::where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->where('effective_date', $date)
            ->first();

        if ($rate) {
            return $rate;
        }

        // Buscar la más reciente anterior a la fecha
        $rate = ExchangeRate::where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($rate) {
            return $rate;
        }

        // Buscar la más reciente posterior a la fecha
        $rate = ExchangeRate::where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->where('effective_date', '>=', $date)
            ->orderBy('effective_date', 'asc')
            ->first();

        return $rate;
    }
}