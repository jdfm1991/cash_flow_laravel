<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class BalanceService
{
    protected int $cacheTtl = 60; // 1 minuto

    /**
     * Obtener el saldo actual de una cuenta bancaria
     */
    public function getBalance(BankAccount $bankAccount): float
    {
        $cacheKey = $this->getCacheKey($bankAccount->id);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($bankAccount) {
            return $this->calculateBalance($bankAccount);
        });
    }

    /**
     * Obtener el saldo en una fecha específica
     */
    public function getBalanceOnDate(BankAccount $bankAccount, string $date): float
    {
        $cacheKey = $this->getCacheKey($bankAccount->id, $date);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($bankAccount, $date) {
            return $this->calculateBalanceOnDate($bankAccount, $date);
        });
    }

    /**
     * Calcular el saldo actual
     */
    public function calculateBalance(BankAccount $bankAccount): float
    {
        $totalIncomes = $bankAccount->transactions()
            ->where('type', 'income')
            ->sum('amount') ?? 0;

        $totalExpenses = $bankAccount->transactions()
            ->where('type', 'expense')
            ->sum('amount') ?? 0;

        return $bankAccount->opening_balance + $totalIncomes - $totalExpenses;
    }

    /**
     * Calcular el saldo en una fecha específica
     */
    public function calculateBalanceOnDate(BankAccount $bankAccount, string $date): float
    {
        $totalIncomes = $bankAccount->transactions()
            ->where('type', 'income')
            ->whereDate('date', '<=', $date)
            ->sum('amount') ?? 0;

        $totalExpenses = $bankAccount->transactions()
            ->where('type', 'expense')
            ->whereDate('date', '<=', $date)
            ->sum('amount') ?? 0;

        return $bankAccount->opening_balance + $totalIncomes - $totalExpenses;
    }

    /**
     * Invalidar caché de una cuenta
     */
    public function invalidateCache(BankAccount $bankAccount): void
    {
        Cache::forget($this->getCacheKey($bankAccount->id));
        Cache::forget($this->getCacheKey($bankAccount->id, 'all'));
    }

    /**
     * Invalidar caché de todas las cuentas de una empresa
     */
    public function invalidateCompanyCache(int $companyId): void
    {
        $accounts = BankAccount::where('company_id', $companyId)->get();
        foreach ($accounts as $account) {
            $this->invalidateCache($account);
        }
    }

    /**
     * Verificar si una cuenta tiene saldo suficiente
     */
    public function hasSufficientBalance(BankAccount $bankAccount, float $amount): bool
    {
        return $this->getBalance($bankAccount) >= $amount;
    }

    /**
     * Obtener el saldo total de todas las cuentas de una empresa
     */
    public function getCompanyTotalBalance(int $companyId): float
    {
        $accounts = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $total = 0;
        foreach ($accounts as $account) {
            $total += $this->getBalance($account);
        }

        return $total;
    }

    /**
     * Obtener clave de caché
     */
    protected function getCacheKey(int $accountId, ?string $date = null): string
    {
        if ($date) {
            return "balance:account:{$accountId}:{$date}";
        }
        return "balance:account:{$accountId}";
    }

    /**
     * Invalidar caché de todas las cuentas
     */
    public function invalidateAllCache(): void
    {
        Cache::flush();
    }
}