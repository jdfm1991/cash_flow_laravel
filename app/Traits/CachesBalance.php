<?php

namespace App\Traits;

use App\Models\BankAccount;
use Illuminate\Support\Facades\Cache;

trait CachesBalance
{
    /**
     * Obtener el saldo de una cuenta desde caché
     */
    public function getCachedBalance(int $bankAccountId): float
    {
        return Cache::remember("balance:account:{$bankAccountId}", 60, function () use ($bankAccountId) {
            $account = BankAccount::find($bankAccountId);
            return $account?->calculateBalance() ?? 0;  // ← Llama a este método
        });
    }

    /**
     * Obtener el saldo en una fecha específica
     */
    public function getCachedBalanceOnDate(int $bankAccountId, string $date): float
    {
        $cacheKey = "balance:account:{$bankAccountId}:date:{$date}";

        return Cache::remember($cacheKey, 3600, function () use ($bankAccountId, $date) {
            $account = BankAccount::find($bankAccountId);
            return $account?->calculateBalanceOnDate($date) ?? 0;  // ← Llama a este método
        });
    }

    /**
     * Invalidar caché de saldo de una cuenta
     */
    public function invalidateBalanceCache(int $bankAccountId): void
    {
        Cache::forget("balance:account:{$bankAccountId}");
        // ✅ Usar forget con patrón
        $keys = Cache::get('balance_keys', []);
        foreach ($keys as $key) {
            if (str_contains($key, "balance:account:{$bankAccountId}:")) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Invalidar caché de todas las cuentas de una empresa
     */
    public function invalidateCompanyBalanceCache(int $companyId): void
    {
        $accounts = BankAccount::where('company_id', $companyId)->pluck('id');
        foreach ($accounts as $accountId) {
            $this->invalidateBalanceCache($accountId);
        }
    }

    /**
     * Invalidar caché de saldo al crear/actualizar/eliminar transacción
     */
    public function invalidateTransactionBalance(array $transaction): void
    {
        if (isset($transaction['bank_account_id'])) {
            $this->invalidateBalanceCache($transaction['bank_account_id']);
        }
    }
}
