<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExchangeRate;
use App\Models\Currency;
use App\Models\User;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        // ================================================================
        // 1. OBTENER MONEDAS Y USUARIO
        // ================================================================

        $ves = Currency::where('code', 'VES')->first();
        $usd = Currency::where('code', 'USD')->first();
        $eur = Currency::where('code', 'EUR')->first();
        $cop = Currency::where('code', 'COP')->first();

        $admin = User::where('email', 'admin@cashflow.com')->first();

        if (!$ves || !$usd) {
            $this->command->error('❌ Monedas VES o USD no encontradas. Ejecuta CurrencySeeder primero.');
            return;
        }

        // ================================================================
        // 2. DEFINIR TASAS DE CAMBIO (Datos históricos y actuales)
        // ================================================================

        $rates = [
            // ============================================================
            // TASA DE CAMBIO: VES → USD (Histórico y actual)
            // ============================================================

            // Tasa actual (la más reciente)
            [
                'from_currency_id' => $ves->id,
                'to_currency_id' => $usd->id,
                'rate' => 517.96000000,
                'effective_date' => now()->toDateString(),
                'source' => 'manual',
                'notes' => 'Tasa actual BCV (manual)',
                'created_by' => $admin?->id,
                'is_current' => true,
            ],
            // Tasa histórica (hace 1 día)
            [
                'from_currency_id' => $ves->id,
                'to_currency_id' => $usd->id,
                'rate' => 515.18000000,
                'effective_date' => now()->subDay()->toDateString(),
                'source' => 'manual',
                'notes' => 'Tasa histórica BCV',
                'created_by' => $admin?->id,
                'is_current' => false,
            ],
            // Tasa histórica (hace 3 días)
            [
                'from_currency_id' => $ves->id,
                'to_currency_id' => $usd->id,
                'rate' => 510.78000000,
                'effective_date' => now()->subDays(3)->toDateString(),
                'source' => 'manual',
                'notes' => 'Tasa histórica BCV',
                'created_by' => $admin?->id,
                'is_current' => false,
            ],
            // Tasa histórica (hace 7 días)
            [
                'from_currency_id' => $ves->id,
                'to_currency_id' => $usd->id,
                'rate' => 500.46000000,
                'effective_date' => now()->subDays(7)->toDateString(),
                'source' => 'manual',
                'notes' => 'Tasa histórica BCV',
                'created_by' => $admin?->id,
                'is_current' => false,
            ],

            // ============================================================
            // TASA DE CAMBIO: VES → EUR (Histórico y actual)
            // ============================================================

            [
                'from_currency_id' => $ves->id,
                'to_currency_id' => $eur->id,
                'rate' => 558.80000000,
                'effective_date' => now()->toDateString(),
                'source' => 'manual',
                'notes' => 'Tasa actual VES/EUR (manual)',
                'created_by' => $admin?->id,
                'is_current' => true,
            ],
            [
                'from_currency_id' => $ves->id,
                'to_currency_id' => $eur->id,
                'rate' => 555.40000000,
                'effective_date' => now()->subDay()->toDateString(),
                'source' => 'manual',
                'notes' => 'Tasa histórica VES/EUR',
                'created_by' => $admin?->id,
                'is_current' => false,
            ],

            // ============================================================
            // TASA DE CAMBIO: VES → COP (Histórico y actual)
            // ============================================================

            [
                'from_currency_id' => $ves->id,
                'to_currency_id' => $cop->id,
                'rate' => 0.12000000,  // 1 VES = 0.12 COP (ejemplo)
                'effective_date' => now()->toDateString(),
                'source' => 'manual',
                'notes' => 'Tasa actual VES/COP (manual)',
                'created_by' => $admin?->id,
                'is_current' => true,
            ],
        ];

        // ================================================================
        // 3. INSERTAR O ACTUALIZAR
        // ================================================================

        foreach ($rates as $rate) {
            ExchangeRate::updateOrCreate(
                [
                    'from_currency_id' => $rate['from_currency_id'],
                    'to_currency_id' => $rate['to_currency_id'],
                    'effective_date' => $rate['effective_date'],
                ],
                $rate
            );
        }

        // ================================================================
        // 4. VERIFICACIÓN
        // ================================================================

        $this->command->info('✅ Tasas de cambio creadas/actualizadas:');
        $this->command->table(
            ['ID', 'De', 'A', 'Tasa', 'Fecha', 'Actual'],
            ExchangeRate::with(['fromCurrency', 'toCurrency'])
                ->orderBy('effective_date', 'desc')
                ->get()
                ->map(fn($rate) => [
                    $rate->id,
                    $rate->fromCurrency?->code ?? 'N/A',
                    $rate->toCurrency?->code ?? 'N/A',
                    number_format($rate->rate, 4),
                    $rate->effective_date,
                    $rate->is_current ? '✅' : '❌',
                ])
                ->toArray()
        );
    }
}