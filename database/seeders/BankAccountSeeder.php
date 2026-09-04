<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Bank;
use App\Models\Currency;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        // ================================================================
        // 1. OBTENER DATOS NECESARIOS
        // ================================================================

        $hcc = Company::where('tax_id', 'J-30553771-2')->first();
        $provincial = Bank::where('code', '0182')->first();
        $mercantil = Bank::where('code', '0105')->first();
        $banesco = Bank::where('code', '0134')->first();
        $bdv = Bank::where('code', '0102')->first();
        $exterior = Bank::where('code', '0115')->first();
        $ves = Currency::where('code', 'VES')->first();
        $usd = Currency::where('code', 'USD')->first();

        if (!$hcc || !$provincial) {
            $this->command->error('❌ Empresa o bancos no encontrados. Ejecuta seeders anteriores.');
            return;
        }

        // ================================================================
        // 2. DEFINIR CUENTAS BANCARIAS
        // ================================================================

        $accounts = [
            // HCC - Cuentas principales
            [
                'company_id' => $hcc->id,
                'bank_id' => $provincial->id,
                'currency_id' => $usd->id,
                'account_number' => '01081028785478962450',
                'alias' => 'Cuenta Principal Provincial',
                'account_type' => 'corriente',
                'account_holder' => 'HOSPITAL DE CLINICAS DE CECIAMB, C.A.',
                'opening_balance' => 547439.13,
                'opened_at' => '2020-01-01',
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'company_id' => $hcc->id,
                'bank_id' => $mercantil->id,
                'currency_id' => $ves->id,
                'account_number' => '01050412457896542574',
                'alias' => 'Cuenta Mercantil',
                'account_type' => 'corriente',
                'account_holder' => 'HOSPITAL DE CLINICAS DE CECIAMB, C.A.',
                'opening_balance' => 294447.89,
                'opened_at' => '2020-01-01',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'company_id' => $hcc->id,
                'bank_id' => $banesco->id,
                'currency_id' => $ves->id,
                'account_number' => '01340147987410325896',
                'alias' => 'Cuenta Banesco',
                'account_type' => 'corriente',
                'account_holder' => 'HOSPITAL DE CLINICAS DE CECIAMB, C.A.',
                'opening_balance' => 45624.53,
                'opened_at' => '2020-01-01',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'company_id' => $hcc->id,
                'bank_id' => $bdv->id,
                'currency_id' => $ves->id,
                'account_number' => '01020145874587962458',
                'alias' => 'Cuenta BDV',
                'account_type' => 'corriente',
                'account_holder' => 'HOSPITAL DE CLINICAS DE CECIAMB, C.A.',
                'opening_balance' => 161532.93,
                'opened_at' => '2020-01-01',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'company_id' => $hcc->id,
                'bank_id' => $exterior->id,
                'currency_id' => $ves->id,
                'account_number' => '01150070620700121480',
                'alias' => 'Cuenta Exterior',
                'account_type' => 'corriente',
                'account_holder' => 'HOSPITAL DE CLINICAS DE CECIAMB, C.A.',
                'opening_balance' => 5324.51,
                'opened_at' => '2020-01-01',
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        // ================================================================
        // 3. INSERTAR O ACTUALIZAR
        // ================================================================

        foreach ($accounts as $account) {
            BankAccount::updateOrCreate(
                [
                    'company_id' => $account['company_id'],
                    'account_number' => $account['account_number'],
                ],
                $account
            );
        }

        $this->command->info('✅ Cuentas bancarias creadas/actualizadas:');
        $this->command->table(
            ['ID', 'Empresa', 'Banco', 'Alias', 'Moneda', 'Saldo'],
            BankAccount::with(['company', 'bank', 'currency'])
                ->get()
                ->map(fn($acc) => [
                    $acc->id,
                    substr($acc->company?->name ?? 'N/A', 0, 20) . '...',
                    $acc->bank?->name ?? 'N/A',
                    $acc->alias,
                    $acc->currency?->code ?? 'N/A',
                    number_format($acc->opening_balance, 2),
                ])
                ->toArray()
        );
    }
}