<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ================================================================
        // LIMPIAR TABLA ANTES DE INSERTAR (evita duplicados)
        // ================================================================
        // Si quieres limpiar la tabla antes de insertar, descomenta:
        // Currency::truncate();

        $currencies = [
            // ============================================================
            // 1. MONEDA BASE (Venezuela - Bolívar)
            // ============================================================
            [
                'code' => 'VES',
                'name' => 'Bolívar',
                'symbol' => 'Bs.S',
                'decimal_places' => 2,
                'is_base' => true,
                'is_default' => false,
                'is_active' => true,
            ],

            // ============================================================
            // 2. MONEDA POR DEFECTO (Visualización - Dólar)
            // ============================================================
            [
                'code' => 'USD',
                'name' => 'Dólar Estadounidense',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_base' => false,
                'is_default' => true,
                'is_active' => true,
            ],

            // ============================================================
            // 3. OTRAS MONEDAS PARA FUTURO MULTI-PAÍS
            // ============================================================
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
                'is_base' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'code' => 'COP',
                'name' => 'Peso Colombiano',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_base' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'code' => 'MXN',
                'name' => 'Peso Mexicano',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_base' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'code' => 'ARS',
                'name' => 'Peso Argentino',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_base' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'code' => 'CLP',
                'name' => 'Peso Chileno',
                'symbol' => '$',
                'decimal_places' => 0,  // El peso chileno no tiene decimales
                'is_base' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'code' => 'PEN',
                'name' => 'Sol Peruano',
                'symbol' => 'S/',
                'decimal_places' => 2,
                'is_base' => false,
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($currencies as $currency) {
            // Usar updateOrCreate para evitar duplicados
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }

        // ================================================================
        // VERIFICACIÓN: Asegurar que solo hay una moneda base
        // ================================================================
        $this->ensureSingleBaseCurrency();

        // ================================================================
        // VERIFICACIÓN: Asegurar que solo hay una moneda por defecto
        // ================================================================
        $this->ensureSingleDefaultCurrency();
    }

    /**
     * Asegurar que solo hay una moneda con is_base = true
     */
    private function ensureSingleBaseCurrency(): void
    {
        $baseCurrencies = Currency::where('is_base', true)->get();

        if ($baseCurrencies->count() > 1) {
            // Dejar solo la primera y desmarcar las demás
            $first = $baseCurrencies->first();
            $baseCurrencies->slice(1)->each(function ($currency) {
                $currency->update(['is_base' => false]);
            });
        }

        if ($baseCurrencies->isEmpty()) {
            // Si no hay moneda base, establecer VES como base
            $ves = Currency::where('code', 'VES')->first();
            if ($ves) {
                $ves->update(['is_base' => true]);
            }
        }
    }

    /**
     * Asegurar que solo hay una moneda con is_default = true
     */
    private function ensureSingleDefaultCurrency(): void
    {
        $defaultCurrencies = Currency::where('is_default', true)->get();

        if ($defaultCurrencies->count() > 1) {
            $first = $defaultCurrencies->first();
            $defaultCurrencies->slice(1)->each(function ($currency) {
                $currency->update(['is_default' => false]);
            });
        }

        if ($defaultCurrencies->isEmpty()) {
            // Si no hay moneda por defecto, establecer USD como default
            $usd = Currency::where('code', 'USD')->first();
            if ($usd) {
                $usd->update(['is_default' => true]);
            }
        }
    }
}