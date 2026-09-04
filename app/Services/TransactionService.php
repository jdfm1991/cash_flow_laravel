<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\Account;
use App\Models\Category;
use App\DTOs\TransactionData;
use App\DTOs\TransferData;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\CurrencyRateNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected BalanceService $balanceService,
        protected ContabilidadIntegrationService $contabilidadService,
    ) {}

    /**
     * Crear una transacción (ingreso o egreso)
     */
    public function create(TransactionData $data): Transaction
    {

        // ✅ Validar que todos los campos requeridos estén presentes
        $required = ['companyId', 'userId', 'type', 'amount', 'currencyId', 'date', 'description', 'accountId', 'categoryId', 'bankAccountId', ];
        foreach ($required as $field) {
            if ($data->$field === null) {
                throw new \InvalidArgumentException("El campo {$field} es requerido para crear una transacción");
            }
        }

        // Validar tipo de transacción
        if (!$data->isValidType()) {
            throw new \InvalidArgumentException('Tipo de transacción inválido');
        }

        // Validar método de pago
        if (!$data->isValidPaymentMethod()) {
            throw new \InvalidArgumentException('Método de pago inválido');
        }

        return DB::transaction(function () use ($data) {
            // 1. Validar cuenta bancaria
            $bankAccount = BankAccount::with('company')
                ->findOrFail($data->bankAccountId);

            // 2. Validar cuenta contable
            $account = Account::with('category')
                ->findOrFail($data->accountId);

            // 3. Validar categoría
            $category = Category::findOrFail($data->categoryId);

            // 4. Validar que la categoría coincida con el tipo
            if ($category->type !== $data->type) {
                throw new \InvalidArgumentException(
                    "El tipo de categoría ({$category->type}) no coincide con el tipo de transacción ({$data->type})"
                );
            }

            // 5. Validar saldo (si es egreso)
            if ($data->type === TransactionType::EXPENSE->value) {
                $this->validateSufficientBalance($bankAccount, $data->amount);
            }

            // 6. Verificar duplicados (hash)
            $hash = $this->generateHash($data);
            $this->validateNoDuplicate($hash);

            // 7. Obtener tasa de cambio y convertir
            $conversion = $this->getConversion($data);

            // 8. Crear la transacción
            $transaction = Transaction::create([
                'company_id' => $data->companyId,
                'user_id' => $data->userId,
                'type' => $data->type,
                'account_id' => $data->accountId,
                'category_id' => $data->categoryId,
                'bank_account_id' => $data->bankAccountId,
                'amount' => $data->amount,
                'currency_id' => $data->currencyId,
                'exchange_rate_id' => $conversion['exchange_rate_id'],
                //'exchange_rate' => $conversion['rate'],
                //'amount_converted' => $conversion['amount'],
                'exchange_rate' => $data->exchangeRate,
                'amount_converted' => $data->amountConverted,
                'date' => $data->date,
                'description' => $data->description,
                'reference' => $data->reference,
                'payment_method' => $data->paymentMethod,
                'receipt_path' => $data->receiptPath,
                'hash' => $hash,
                'source' => 'manual',
            ]);

            // 9. Invalidar caché de saldo
            $this->balanceService->invalidateCache($bankAccount);

            // 10. Registrar en auditoría (opcional)
            Log::info("Transacción creada", [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'user_id' => $data->userId,
                'company_id' => $data->companyId,
            ]);

            // 11. Generar asiento contable (si está configurado)
            if (config('contabilidad.automatica', false)) {
                try {
                    $this->contabilidadService->generateEntry($transaction);
                } catch (\Exception $e) {
                    Log::error("Error generando asiento contable: " . $e->getMessage(), [
                        'transaction_id' => $transaction->id,
                    ]);
                    // No lanzamos excepción para no interrumpir la transacción
                }
            }

            return $transaction->load(['account', 'category', 'bankAccount', 'currency']);
        });
    }

    /**
     * Crear una transferencia entre cuentas
     */
    public function createTransfer(TransferData $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Validar cuentas origen y destino
            $fromAccount = BankAccount::findOrFail($data->fromAccountId);
            $toAccount = BankAccount::findOrFail($data->toAccountId);

            // 2. Validar que sean diferentes
            if ($data->fromAccountId === $data->toAccountId) {
                throw new \InvalidArgumentException('La cuenta origen y destino deben ser diferentes');
            }

            // 3. Validar que pertenezcan a la misma empresa
            if (
                $fromAccount->company_id !== $data->companyId ||
                $toAccount->company_id !== $data->companyId
            ) {
                throw new \InvalidArgumentException('Las cuentas deben pertenecer a la misma empresa');
            }

            // 4. Validar saldo en origen
            $this->validateSufficientBalance($fromAccount, $data->amount);

            // 5. Validar moneda
            if ($fromAccount->currency_id !== $toAccount->currency_id) {
                throw new \InvalidArgumentException('Las cuentas deben tener la misma moneda');
            }

            // 6. Obtener cuenta contable para transferencias
            $transferAccount = $this->getTransferAccount($data->companyId);

            // 7. Crear egreso en origen
            $expenseData = new TransactionData(
                companyId: $data->companyId,
                userId: $data->userId,
                type: TransactionType::EXPENSE->value,
                amount: $data->amount,
                currencyId: $fromAccount->currency_id,
                date: $data->date,
                description: "Transferencia a cuenta #{$toAccount->id}: {$data->description}",
                accountId: $transferAccount['expense']->id,
                categoryId: $transferAccount['expense']->category_id,
                bankAccountId: $data->fromAccountId,
                reference: $data->reference,
            );

            $expense = $this->create($expenseData);

            // 8. Crear ingreso en destino
            $incomeData = new TransactionData(
                companyId: $data->companyId,
                userId: $data->userId,
                type: TransactionType::INCOME->value,
                amount: $data->amount,
                currencyId: $toAccount->currency_id,
                date: $data->date,
                description: "Transferencia desde cuenta #{$fromAccount->id}: {$data->description}",
                accountId: $transferAccount['income']->id,
                categoryId: $transferAccount['income']->category_id,
                bankAccountId: $data->toAccountId,
                reference: $data->reference,
            );

            $income = $this->create($incomeData);

            // 9. Vincular ambas transacciones
            $expense->update(['transfer_id' => $income->id]);
            $income->update(['transfer_id' => $expense->id]);

            return [
                'expense' => $expense,
                'income' => $income,
            ];
        });
    }

    /**
     * Actualizar una transacción
     */
    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        // Validar que la transacción no esté conciliada
        if ($transaction->is_reconciled) {
            throw new \InvalidArgumentException('No se puede modificar una transacción conciliada');
        }

        return DB::transaction(function () use ($transaction, $data) {
            // ✅ Solo actualizar campos que han sido enviados
            $updateData = [];

            // Campos que pueden actualizarse
            $fields = [
                'account_id',
                'category_id',
                'bank_account_id',
                'amount',
                'currency_id',
                'date',
                'description',
                'reference',
                'payment_method',
                'receipt_path'
            ];

            foreach ($fields as $field) {
                $value = match ($field) {
                    'account_id' => $data->accountId ?? null,
                    'category_id' => $data->categoryId ?? null,
                    'bank_account_id' => $data->bankAccountId ?? null,
                    'amount' => $data->amount ?? null,
                    'currency_id' => $data->currencyId ?? null,
                    'date' => $data->date ?? null,
                    'description' => $data->description ?? null,
                    'reference' => $data->reference ?? null,
                    'payment_method' => $data->paymentMethod ?? null,
                    'receipt_path' => $data->receiptPath ?? null,
                    default => null,
                };

                // ✅ Solo agregar si el valor no es null
                if ($value !== null && !is_null($value)) {
                    $updateData[$field] = $value;
                }
            }

            // ✅ Si no hay datos para actualizar, lanzar excepción
            if (empty($updateData)) {
                throw new \InvalidArgumentException('No hay datos válidos para actualizar');
            }

            // Si cambia el monto o la cuenta, invalidar caché antes
            if (isset($updateData['amount']) || isset($updateData['bank_account_id'])) {
                $oldBankAccount = BankAccount::find($transaction->bank_account_id);
                if ($oldBankAccount) {
                    $this->balanceService->invalidateCache($oldBankAccount);
                }
            }

            // Actualizar la transacción
            $transaction->update($updateData);

            // Invalidar caché de la nueva cuenta (si se cambió)
            if (isset($updateData['bank_account_id'])) {
                $newBankAccount = BankAccount::find($updateData['bank_account_id']);
                if ($newBankAccount) {
                    $this->balanceService->invalidateCache($newBankAccount);
                }
            }

            Log::info("Transacción actualizada", [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
            ]);

            return $transaction->fresh();
        });
    }

    /**
     * Eliminar una transacción (soft delete)
     */
    public function delete(Transaction $transaction): bool
    {
        // Validar que la transacción no esté conciliada
        if ($transaction->is_reconciled) {
            throw new \InvalidArgumentException('No se puede eliminar una transacción conciliada');
        }

        return DB::transaction(function () use ($transaction) {
            // 1. Invalidar caché antes de eliminar
            $bankAccount = BankAccount::find($transaction->bank_account_id);
            if ($bankAccount) {
                $this->balanceService->invalidateCache($bankAccount);
            }

            // 2. Eliminar (soft delete)
            $result = $transaction->delete();

            // 3. Registrar en auditoría
            Log::info("Transacción eliminada", [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
            ]);

            return $result;
        });
    }

    /**
     * Validar saldo suficiente
     */
    protected function validateSufficientBalance(BankAccount $bankAccount, float $amount): void
    {
        $balance = $bankAccount->current_balance;
        if ($balance < $amount) {
            throw new InsufficientBalanceException(
                amount: $amount,
                balance: $balance,
                accountNumber: $bankAccount->account_number
            );
        }
    }

    /**
     * Validar que no haya duplicados
     */
    protected function validateNoDuplicate(string $hash): void
    {
        $existing = Transaction::where('hash', $hash)->first();
        if ($existing) {
            throw new DuplicateTransactionException($hash, $existing->id);
        }
    }

    /**
     * Generar hash único para la transacción
     */
    protected function generateHash(TransactionData $data): string
    {
        $string = implode('|', [
            $data->companyId,
            $data->amount,
            $data->date,
            $data->type,
            $data->bankAccountId,
            $data->reference ?? '',
        ]);

        return md5($string);
    }

    /**
     * Obtener conversión de moneda
     */
    protected function getConversion(TransactionData $data): array
    {
        $baseCurrency = $this->currencyService->getBaseCurrency();

        if ($data->currencyId === $baseCurrency->id) {
            return [
                'amount' => $data->amount,
                'rate' => 1.0,
                'exchange_rate_id' => null,
            ];
        }

        return $this->currencyService->convertWithReference(
            amount: $data->amount,
            fromCurrencyId: $data->currencyId,
            toCurrencyId: $baseCurrency->id,
            date: $data->date
        );
    }

    /**
     * Obtener cuenta contable para transferencias
     */
    protected function getTransferAccount(int $companyId): array
    {
        // Buscar cuenta para egresos (transferencias origen)
        $expenseAccount = Account::where('name', 'Transferencias entre cuentas')
            ->whereHas('category', function ($query) {
                $query->where('type', 'expense');
            })
            ->first();

        // Buscar cuenta para ingresos (transferencias destino)
        $incomeAccount = Account::where('name', 'Transferencias entre cuentas')
            ->whereHas('category', function ($query) {
                $query->where('type', 'income');
            })
            ->first();

        // Crear si no existen (sistema)
        if (!$expenseAccount) {
            $category = Category::where('name', 'Otros Egresos')
                ->where('type', 'expense')
                ->first();

            $expenseAccount = Account::create([
                'name' => 'Transferencias entre cuentas',
                'category_id' => $category?->id,
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        if (!$incomeAccount) {
            $category = Category::where('name', 'Otros Ingresos')
                ->where('type', 'income')
                ->first();

            $incomeAccount = Account::create([
                'name' => 'Transferencias entre cuentas',
                'category_id' => $category?->id,
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        return [
            'expense' => $expenseAccount,
            'income' => $incomeAccount,
        ];
    }
}
