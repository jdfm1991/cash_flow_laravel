<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\User;
use App\Models\BankAccount;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\ExchangeRate;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        // ================================================================
        // 1. OBTENER DATOS NECESARIOS CON VERIFICACIONES
        // ================================================================

        $company = Company::first();
        $user = User::first();
        $bankAccount = BankAccount::first();
        $account = Account::first();
        $category = Category::first();
        $currency = Currency::where('code', 'VES')->first();
        $exchangeRate = ExchangeRate::where('from_currency_id', $currency?->id)->first();

        // ================================================================
        // 2. VERIFICAR QUE EXISTAN LOS DATOS BASE
        // ================================================================

        if (!$company) {
            $this->command->error('❌ No hay empresas. Ejecuta CompanySeeder primero.');
            return;
        }

        if (!$user) {
            $this->command->error('❌ No hay usuarios. Ejecuta UserSeeder primero.');
            return;
        }

        if (!$bankAccount) {
            $this->command->error('❌ No hay cuentas bancarias. Ejecuta BankAccountSeeder primero.');
            return;
        }

        if (!$account) {
            $this->command->error('❌ No hay cuentas contables. Ejecuta AccountSeeder primero.');
            return;
        }

        if (!$category) {
            $this->command->error('❌ No hay categorías. Ejecuta CategorySeeder primero.');
            return;
        }

        if (!$currency) {
            $this->command->error('❌ No hay monedas. Ejecuta CurrencySeeder primero.');
            return;
        }

        // ================================================================
        // 3. CREAR TRANSACCIONES DE PRUEBA
        // ================================================================

        $transactions = [
            // ============================================================
            // INGRESOS
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'bank_account_id' => $bankAccount->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'income',
                'amount' => 1000.00,
                'currency_id' => $currency->id,
                'exchange_rate_id' => $exchangeRate?->id,
                'exchange_rate' => $exchangeRate?->rate ?? 1,
                'amount_base_currency' => 1000.00,
                'date' => now()->subDays(5),
                'description' => 'Ingreso de prueba',
                'reference' => 'REF-001',
                'payment_method' => 'bank',
                'source' => 'manual',
            ],
            // ============================================================
            // EGRESOS
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'bank_account_id' => $bankAccount->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 500.00,
                'currency_id' => $currency->id,
                'exchange_rate_id' => $exchangeRate?->id,
                'exchange_rate' => $exchangeRate?->rate ?? 1,
                'amount_base_currency' => 500.00,
                'date' => now()->subDays(3),
                'description' => 'Egreso de prueba',
                'reference' => 'REF-002',
                'payment_method' => 'bank',
                'source' => 'manual',
            ],
            // ============================================================
            // OTRO INGRESO
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'bank_account_id' => $bankAccount->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'income',
                'amount' => 2500.00,
                'currency_id' => $currency->id,
                'exchange_rate_id' => $exchangeRate?->id,
                'exchange_rate' => $exchangeRate?->rate ?? 1,
                'amount_base_currency' => 2500.00,
                'date' => now()->subDays(2),
                'description' => 'Ingreso adicional de prueba',
                'reference' => 'REF-003',
                'payment_method' => 'bank',
                'source' => 'manual',
            ],
            // ============================================================
            // OTRO EGRESO
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'bank_account_id' => $bankAccount->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 300.00,
                'currency_id' => $currency->id,
                'exchange_rate_id' => $exchangeRate?->id,
                'exchange_rate' => $exchangeRate?->rate ?? 1,
                'amount_base_currency' => 300.00,
                'date' => now()->subDay(),
                'description' => 'Egreso adicional de prueba',
                'reference' => 'REF-004',
                'payment_method' => 'cash',
                'source' => 'manual',
            ],
        ];

        // ================================================================
        // 4. GENERAR HASH Y CREAR TRANSACCIONES
        // ================================================================

        $created = 0;

        foreach ($transactions as $transaction) {
            // Generar hash único para evitar duplicados
            $transaction['hash'] = md5(
                $transaction['company_id'] .
                $transaction['amount'] .
                $transaction['date'] .
                $transaction['type'] .
                ($transaction['reference'] ?? '')
            );

            // Crear la transacción
            Transaction::create($transaction);
            $created++;
        }

        // ================================================================
        // 5. VERIFICACIÓN
        // ================================================================

        $this->command->info("✅ {$created} transacciones de prueba creadas:");
        $this->command->table(
            ['ID', 'Tipo', 'Monto', 'Moneda', 'Fecha', 'Descripción'],
            Transaction::with(['currency'])
                ->orderBy('date', 'desc')
                ->get()
                ->map(fn($t) => [
                    $t->id,
                    ucfirst($t->type),
                    number_format($t->amount, 2),
                    $t->currency?->code ?? 'N/A',
                    $t->date,
                    substr($t->description ?? 'N/A', 0, 20) . '...',
                ])
                ->toArray()
        );
    }
}