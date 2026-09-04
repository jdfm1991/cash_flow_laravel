<?php

namespace App\Services;

use App\Models\BankAccount;
use App\DTOs\BankAccountData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BankAccountService
{
    public function __construct(
        protected BalanceService $balanceService,
    ) {}

    /**
     * Obtener cuentas bancarias por empresa
     */
    public function getByCompany(int $companyId, bool $onlyActive = true)
    {
        $query = BankAccount::with(['bank', 'currency'])
            ->where('company_id', $companyId);

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->orderBy('is_default', 'desc')
            ->orderBy('alias')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'alias' => $account->alias,
                    'account_number' => $account->account_number,
                    'account_type' => $account->account_type,
                    'account_type_label' => $account->account_type_label,
                    'account_holder' => $account->account_holder,
                    'opening_balance' => $account->opening_balance,
                    'current_balance' => $this->balanceService->getBalance($account),
                    'bank' => [
                        'id' => $account->bank?->id,
                        'name' => $account->bank?->name,
                        'code' => $account->bank?->code,
                    ],
                    'currency' => [
                        'id' => $account->currency?->id,
                        'code' => $account->currency?->code,
                        'symbol' => $account->currency?->symbol,
                    ],
                    'is_active' => $account->is_active,
                    'is_default' => $account->is_default,
                ];
            });
    }

    /**
     * Obtener una cuenta bancaria por ID
     */
    public function findById(int $id): ?BankAccount
    {
        return BankAccount::with(['bank', 'currency'])->find($id);
    }

    /**
     * Crear una cuenta bancaria
     */
    public function create(BankAccountData $data): BankAccount
    {
        // Verificar que el número de cuenta no esté duplicado
        $existing = BankAccount::where('company_id', $data->companyId)
            ->where('account_number', $data->accountNumber)
            ->first();

        if ($existing) {
            throw new \Exception('El número de cuenta ya está registrado para esta empresa');
        }

        // Si es la primera cuenta o es default, marcar como default
        $isDefault = $data->isDefault;
        if (!$isDefault) {
            $count = BankAccount::where('company_id', $data->companyId)->count();
            if ($count === 0) {
                $isDefault = true;
            }
        }

        $account = BankAccount::create([
            'company_id' => $data->companyId,
            'bank_id' => $data->bankId,
            'currency_id' => $data->currencyId,
            'account_number' => $data->accountNumber,
            'alias' => $data->alias,
            'account_type' => $data->accountType,
            'account_holder' => $data->accountHolder,
            'opening_balance' => $data->openingBalance ?? 0,
            'opened_at' => $data->openedAt,
            'notes' => $data->notes,
            'is_active' => $data->isActive ?? true,
            'is_default' => $isDefault,
        ]);

        // Si es default, quitar default de otras cuentas
        if ($isDefault) {
            BankAccount::where('company_id', $data->companyId)
                ->where('id', '!=', $account->id)
                ->update(['is_default' => false]);
        }

        Log::info("Cuenta bancaria creada", [
            'account_id' => $account->id,
            'alias' => $account->alias,
            'company_id' => $data->companyId,
            'user_id' => auth()->id(),
        ]);

        return $account->fresh();
    }

    /**
     * Actualizar una cuenta bancaria
     */
    public function update(BankAccount $account, BankAccountData $data): BankAccount
    {
        // ✅ Usar el método toArray() del DTO
        $updateData = $data->toArray();

        // ✅ Si no hay datos para actualizar, lanzar excepción
        if (empty($updateData)) {
            throw new \Exception('No hay datos para actualizar');
        }

        // Verificar duplicado si se cambia el número
        if (isset($updateData['account_number']) && $updateData['account_number'] !== $account->account_number) {
            $existing = BankAccount::where('company_id', $account->company_id)
                ->where('account_number', $updateData['account_number'])
                ->where('id', '!=', $account->id)
                ->first();

            if ($existing) {
                throw new \Exception('El número de cuenta ya está registrado para esta empresa');
            }
        }

        // Manejar is_default
        if (isset($updateData['is_default']) && $updateData['is_default']) {
            // Desmarcar otras cuentas como default
            BankAccount::where('company_id', $account->company_id)
                ->where('id', '!=', $account->id)
                ->update(['is_default' => false]);
        }

        $account->update($updateData);

        // Invalidar caché de saldo
        $this->balanceService->invalidateCache($account);

        Log::info("Cuenta bancaria actualizada", [
            'account_id' => $account->id,
            'alias' => $account->alias,
            'user_id' => auth()->id(),
        ]);

        return $account->fresh();
    }

    /**
     * Eliminar una cuenta bancaria
     */
    public function delete(BankAccount $account): bool
    {
        // Verificar si tiene transacciones asociadas
        $transactionCount = $account->transactions()->count();

        if ($transactionCount > 0) {
            throw new \Exception("No se puede eliminar la cuenta bancaria porque tiene {$transactionCount} transacciones asociadas");
        }

        $result = $account->delete();

        // Invalidar caché de saldo
        $this->balanceService->invalidateCache($account);

        Log::info("Cuenta bancaria eliminada", [
            'account_id' => $account->id,
            'alias' => $account->alias,
            'user_id' => auth()->id(),
        ]);

        return $result;
    }

    /**
     * Activar/Desactivar una cuenta bancaria
     */
    public function toggle(BankAccount $account): BankAccount
    {
        $account->is_active = !$account->is_active;
        $account->save();

        // Invalidar caché de saldo
        $this->balanceService->invalidateCache($account);

        return $account->fresh();
    }

    /**
     * Establecer una cuenta como predeterminada
     */
    public function setDefault(BankAccount $account): BankAccount
    {
        // Desmarcar todas las cuentas como default
        BankAccount::where('company_id', $account->company_id)
            ->update(['is_default' => false]);

        // Marcar esta cuenta como default
        $account->is_default = true;
        $account->save();

        Log::info("Cuenta bancaria establecida como predeterminada", [
            'account_id' => $account->id,
            'alias' => $account->alias,
        ]);

        return $account->fresh();
    }

    /**
     * Obtener el saldo de una cuenta
     */
    public function getBalance(BankAccount $account): float
    {
        return $this->balanceService->getBalance($account);
    }

    /**
     * Obtener el saldo en una fecha específica
     */
    public function getBalanceOnDate(BankAccount $account, string $date): float
    {
        return $this->balanceService->getBalanceOnDate($account, $date);
    }

    /**
     * Obtener resumen de todas las cuentas de una empresa
     */
    public function getSummary(int $companyId): array
    {
        $accounts = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['bank', 'currency'])
            ->get();

        $summary = [];
        $totalBalance = 0;

        foreach ($accounts as $account) {
            $balance = $this->balanceService->getBalance($account);
            $totalBalance += $balance;

            $summary[] = [
                'id' => $account->id,
                'alias' => $account->alias,
                'account_number' => $account->account_number,
                'bank' => $account->bank?->name,
                'currency' => $account->currency?->code,
                'balance' => $balance,
                'type' => $account->account_type_label,
                'is_default' => $account->is_default,
            ];
        }

        return [
            'total_balance' => $totalBalance,
            'accounts' => $summary,
        ];
    }
}
