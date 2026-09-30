<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * ✅ Preview: Tasas a importar desde transacciones (sin guardar)
     */
    public function previewImportFromTransactions(): array
    {
        $baseCurrency = Currency::where('is_base', true)->first();
        $defaultCurrency = Currency::where('is_default', true)->first();

        if (!$baseCurrency) {
            return [
                'success' => false,
                'message' => 'No se encontró la moneda base del sistema.',
                'total' => 0,
                'new' => 0,
                'existing' => 0,
                'items' => [],
                'base_currency' => null,
            ];
        }

        // Obtener tasas únicas desde transactions
        $rates = Transaction::select('date', 'exchange_rate', 'currency_id')
            ->whereNotNull('exchange_rate')
            ->whereNotNull('currency_id')
            ->where('exchange_rate', '>', 0)
            ->where('currency_id', '=', $baseCurrency->id)
            ->distinct()
            ->orderBy('date', 'asc')
            ->get();

        $new = 0;
        $existing = 0;
        $items = [];

        // Obtener todas las tasas existentes para eficiencia
        $existingRates = ExchangeRate::where('to_currency_id', $baseCurrency->id)
            ->select('from_currency_id', 'effective_date')
            ->get()
            ->map(function ($item) {
                return $item->from_currency_id . '_' . $item->effective_date;
            })
            ->toArray();

        $existingSet = array_flip($existingRates);

        foreach ($rates as $rate) {
            $key = $rate->currency_id . '_' . $rate->date;

            if (isset($existingSet[$key])) {
                $existing++;
            } else {
                $new++;

                // Obtener información de la moneda
                $currency = Currency::find($rate->currency_id);

                $items[] = [
                    'date' => $rate->date,
                    'rate' => (float) $rate->exchange_rate,
                    'currency_id' => $rate->currency_id,
                    'currency_code' => $currency?->code ?? 'N/A',
                    'currency_name' => $currency?->name ?? 'N/A',
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Preview generado correctamente.',
            'total' => $rates->count(),
            'new' => $new,
            'existing' => $existing,
            'items' => array_slice($items, 0, 10),
            'base_currency' => $baseCurrency->code,
        ];
    }

    /**
     * ✅ Importar tasas desde transacciones existentes
     */
    public function importFromTransactions(): array
    {
        $stats = [
            'success' => true,
            'total_found' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => [],
        ];

        try {
            $baseCurrency = Currency::where('is_base', true)->first();
            $defaultCurrency = Currency::where('is_default', true)->first();

            if (!$defaultCurrency) {
                throw new \Exception('No se encontró la moneda base del sistema.');
            }

            // Obtener tasas únicas desde transactions
            $rates = Transaction::select('date', 'exchange_rate', 'currency_id')
                ->whereNotNull('exchange_rate')
                ->whereNotNull('currency_id')
                ->where('exchange_rate', '>', 0)
                ->where('currency_id', '=', $baseCurrency->id)
                ->distinct()
                ->orderBy('date', 'asc')
                ->get();

            $stats['total_found'] = $rates->count();

            // Obtener todas las tasas existentes para eficiencia
            $existingRates = ExchangeRate::where('to_currency_id', $baseCurrency->id)
                ->select('from_currency_id', 'effective_date')
                ->get()
                ->map(function ($item) {
                    return $item->from_currency_id . '_' . $item->effective_date;
                })
                ->toArray();

            $existingSet = array_flip($existingRates);

            DB::beginTransaction();

            foreach ($rates as $rate) {
                try {
                    $key = $rate->currency_id . '_' . $rate->date;

                    if (isset($existingSet[$key])) {
                        $stats['skipped']++;
                        continue;
                    }

                    // Crear la tasa
                    ExchangeRate::create([
                        'from_currency_id' => $baseCurrency->id,
                        'to_currency_id' => $defaultCurrency->id,
                        'rate' => $rate->exchange_rate,
                        'effective_date' => $rate->date,
                        'source' => 'system',
                        'is_current' => false,
                        'notes' => 'Importada desde transacciones existentes',
                    ]);

                    $stats['imported']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $stats['details'][] = "Fecha {$rate->date}: {$e->getMessage()}";

                    Log::error('Error importando tasa', [
                        'date' => $rate->date,
                        'rate' => $rate->exchange_rate,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Actualizar cuál es la tasa actual para cada par
            $this->updateCurrentRates();

            DB::commit();

            Log::info('Importación de tasas completada', $stats);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error en importFromTransactions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $stats['success'] = false;
            $stats['details'][] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Actualizar cuál es la tasa actual para cada par
     */
    protected function updateCurrentRates(): void
    {
        $pairs = ExchangeRate::select('from_currency_id', 'to_currency_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            // Marcar todas como no actuales
            ExchangeRate::where('from_currency_id', $pair->from_currency_id)
                ->where('to_currency_id', $pair->to_currency_id)
                ->update(['is_current' => false]);

            // Marcar la más reciente como actual
            $latest = ExchangeRate::where('from_currency_id', $pair->from_currency_id)
                ->where('to_currency_id', $pair->to_currency_id)
                ->orderBy('effective_date', 'desc')
                ->first();

            if ($latest) {
                $latest->update(['is_current' => true]);
            }
        }
    }
}