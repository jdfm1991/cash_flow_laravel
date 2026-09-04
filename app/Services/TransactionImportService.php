<?php

namespace App\Services;

use App\Models\ImportedTransaction;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\BankAccount;
use App\Models\Currency;
use App\DTOs\TransactionData;
use App\Enums\TransactionType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TransactionImportService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected CurrencyService $currencyService,
    ) {}

    /**
     * Procesar todas las transacciones importadas de una sesión
     */
    public function processImportedTransactions(string $sessionId): array
    {
        $results = [
            'processed' => 0,
            'errors' => 0,
            'duplicates' => 0,
            'error_messages' => [],
            'transactions' => [],
        ];

        // Obtener transacciones importadas no procesadas
        $imported = ImportedTransaction::where('import_session_id', $sessionId)
            ->where('is_processed', false)
            ->get();

        Log::info('🔄 Procesando transacciones importadas', [
            'session_id' => $sessionId,
            'total' => $imported->count(),
        ]);

        foreach ($imported as $item) {
            try {
                // Verificar si ya existe una transacción real con el mismo hash
                $existing = Transaction::where('hash', $item->hash)->first();
                if ($existing) {
                    $results['duplicates']++;
                    Log::info('⏭️ Transacción duplicada omitida', [
                        'hash' => $item->hash,
                        'existing_id' => $existing->id,
                    ]);
                    continue;
                }

                // Convertir a transacción real
                $transaction = $this->convertToTransaction($item);
                
                // Marcar como procesada
                $item->update([
                    'is_processed' => true,
                    'mapped_account_id' => $transaction->account_id,
                ]);

                $results['processed']++;
                $results['transactions'][] = $transaction;

                Log::info('✅ Transacción importada procesada', [
                    'imported_id' => $item->id,
                    'transaction_id' => $transaction->id,
                ]);

            } catch (\Exception $e) {
                $results['errors']++;
                $results['error_messages'][] = "ID {$item->id}: " . $e->getMessage();
                
                Log::error('❌ Error procesando transacción importada', [
                    'imported_id' => $item->id,
                    'error' => $e->getMessage(),
                    'data' => $item->toArray(),
                ]);

                // Guardar error en el registro
                $item->update([
                    'error_log' => $e->getMessage(),
                ]);
            }
        }

        Log::info('📊 Resumen de procesamiento de importación', [
            'session_id' => $sessionId,
            'processed' => $results['processed'],
            'duplicates' => $results['duplicates'],
            'errors' => $results['errors'],
        ]);

        return $results;
    }

    /**
     * Convertir una transacción importada a transacción real
     */
    protected function convertToTransaction(ImportedTransaction $item): Transaction
    {
        return DB::transaction(function () use ($item) {
            // 1. Obtener o crear cuenta contable
            $account = $this->getOrCreateAccount($item);

            // 2. Obtener categoría
            $category = $this->getCategory($item);

            // 3. Obtener cuenta bancaria
            $bankAccount = $this->getBankAccount($item);

            // 4. Obtener moneda
            $currency = $this->getCurrency($item);

            // 5. Obtener tasa de cambio (si aplica)
            $exchangeRate = $this->getExchangeRate($item, $currency);

            // 6. Calcular monto en moneda base
            $amountBaseCurrency = $this->calculateBaseAmount($item, $currency, $exchangeRate);

            // 7. Crear TransactionData
            $data = new TransactionData(
                companyId: $item->company_id,
                userId: $this->getDefaultUserId($item),
                type: $item->transaction_type,
                amount: $item->amount,
                currencyId: $currency->id,
                date: $item->transaction_date,
                description: $item->description ?? 'Importado desde sistema externo',
                accountId: $account->id,
                categoryId: $category->id,
                bankAccountId: $bankAccount->id,
                reference: $item->reference,
                paymentMethod: 'bank',
                exchangeRate: $item->exchange_rate,
                amountConverted: $item->amount_converted
            );

            // 8. Crear la transacción real
            $transaction = $this->transactionService->create($data);

            // 9. Actualizar el hash en la transacción real
            $transaction->update(['hash' => $item->hash]);

            return $transaction;
        });
    }

    /**
     * Obtener o crear cuenta contable
     */
    protected function getOrCreateAccount(ImportedTransaction $item): Account
    {
        // Buscar cuenta existente por nombre
        $account = Account::where('name', $item->description)
            ->orWhere('name', 'LIKE', '%' . substr($item->description, 0, 30) . '%')
            ->first();

        if ($account) {
            return $account;
        }

        // Buscar categoría base para el tipo de transacción
        $category = Category::where('type', $item->transaction_type)
            ->where('is_system', true)
            ->first();

        // Crear cuenta genérica
        return Account::create([
            'name' => substr($item->description, 0, 100) ?: 'Cuenta Importada',
            'category_id' => $category?->id,
            'description' => 'Cuenta creada automáticamente durante importación',
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Obtener categoría
     */
    protected function getCategory(ImportedTransaction $item): Category
    {
        // Si tiene categoría mapeada
        if ($item->mapped_category) {
            $category = Category::where('name', $item->mapped_category)->first();
            if ($category) {
                return $category;
            }
        }

        // Buscar por descripción
        $category = Category::where('type', $item->transaction_type)
            ->where('name', 'LIKE', '%' . substr($item->description, 0, 30) . '%')
            ->first();

        if ($category) {
            return $category;
        }

        // Categoría por defecto
        $default = Category::where('type', $item->transaction_type)
            ->where('is_system', true)
            ->first();

        if ($default) {
            return $default;
        }

        // Crear categoría por defecto si no existe
        return Category::create([
            'name' => $item->transaction_type === 'income' ? 'Otros Ingresos' : 'Otros Egresos',
            'type' => $item->transaction_type,
            'is_system' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Obtener cuenta bancaria
     */
    protected function getBankAccount(ImportedTransaction $item): BankAccount
    {
        if ($item->bank_account_id) {
            $account = BankAccount::find($item->bank_account_id);
            if ($account) {
                return $account;
            }
        }

        // Buscar cuenta por defecto de la empresa
        $account = BankAccount::where('company_id', $item->company_id)
            ->where('is_default', true)
            ->first();

        if ($account) {
            return $account;
        }

        // Buscar primera cuenta activa
        $account = BankAccount::where('company_id', $item->company_id)
            ->where('is_active', true)
            ->first();

        if ($account) {
            return $account;
        }

        throw new \Exception("No se encontró una cuenta bancaria válida para la empresa {$item->company_id}");
    }

    /**
     * Obtener moneda
     */
    protected function getCurrency(ImportedTransaction $item): Currency
    {
        // Si tiene moneda original
        if ($item->original_currency) {
            $currency = Currency::where('code', $item->original_currency)->first();
            if ($currency) {
                return $currency;
            }
        }

        // Obtener cuenta bancaria para saber su moneda
        $bankAccount = BankAccount::find($item->bank_account_id);
        if ($bankAccount && $bankAccount->currency) {
            return $bankAccount->currency;
        }

        // Moneda por defecto (base)
        $base = Currency::where('is_base', true)->first();
        if ($base) {
            return $base;
        }

        // Primera moneda activa
        return Currency::where('is_active', true)->first();
    }

    /**
     * Obtener tasa de cambio
     */
    protected function getExchangeRate(ImportedTransaction $item, Currency $currency): ?float
    {
        $baseCurrency = Currency::where('is_base', true)->first();
        
        if (!$baseCurrency || $currency->id == $baseCurrency->id) {
            return 1.0;
        }

        if ($item->exchange_rate) {
            return (float) $item->exchange_rate;
        }

        // Buscar tasa en el sistema
        try {
            return $this->currencyService->getRate(
                $currency->id,
                $baseCurrency->id,
                $item->transaction_date
            );
        } catch (\Exception $e) {
            Log::warning("No se encontró tasa de cambio para la fecha", [
                'date' => $item->transaction_date,
                'currency' => $currency->code,
            ]);
            return 1.0;
        }
    }

    /**
     * Calcular monto en moneda base
     */
    protected function calculateBaseAmount(ImportedTransaction $item, Currency $currency, ?float $exchangeRate): float
    {
        $baseCurrency = Currency::where('is_base', true)->first();
        
        if (!$baseCurrency || $currency->id == $baseCurrency->id) {
            return $item->amount;
        }

        $rate = $exchangeRate ?? 1.0;
        return round($item->amount * $rate, 4);
    }

    /**
     * Obtener ID de usuario por defecto
     */
    protected function getDefaultUserId(ImportedTransaction $item): int
    {
        // Buscar el usuario que creó la importación
        $session = \App\Models\ImportSession::find($item->import_session_id);
        if ($session && $session->user_id) {
            return $session->user_id;
        }

        // Buscar cualquier usuario de la empresa
        $user = \App\Models\User::where('company_id', $item->company_id)->first();
        if ($user) {
            return $user->id;
        }

        // Usuario por defecto (ID 1)
        return 1;
    }
}