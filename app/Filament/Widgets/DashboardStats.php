<?php

namespace App\Filament\Widgets;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $companyId = auth()->user()?->current_company_id;

        if (!$companyId && auth()->user()->hasRole('super_admin')) {
            return $this->getGlobalStats();
        }

        if (!$companyId) {
            return $this->getEmptyStats();
        }

        return $this->getCompanyStats($companyId);
    }

    /**
     * ✅ Estadísticas por empresa (últimos 30 días CON DATOS)
     */
    protected function getCompanyStats(int $companyId): array
    {
        // ✅ 1. Obtener la fecha de la última transacción
        $lastTransactionDate = Transaction::where('company_id', $companyId)
            ->orderBy('date', 'desc')
            ->value('date');

        // ✅ 2. Si no hay transacciones, mostrar mensaje
        if (!$lastTransactionDate) {
            return $this->getEmptyStats();
        }

        // ✅ 3. Calcular los últimos 30 días a partir de la última transacción
        $endDate = Carbon::parse($lastTransactionDate)->toDateString();
        $startDate = Carbon::parse($lastTransactionDate)->subDays(29)->toDateString();

        $incomeQuery = Transaction::where('company_id', $companyId)
            ->where('type', 'income')
            ->whereBetween('date', [$startDate, $endDate]);

        $expenseQuery = Transaction::where('company_id', $companyId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate]);

        $totalIncomeOriginal = $incomeQuery->sum('amount');
        $totalExpenseOriginal = $expenseQuery->sum('amount');
        $netBalanceOriginal = $totalIncomeOriginal - $totalExpenseOriginal;

        $totalIncomeConverted = $incomeQuery->sum('amount_converted');
        $totalExpenseConverted = $expenseQuery->sum('amount_converted');
        $netBalanceConverted = $totalIncomeConverted - $totalExpenseConverted;

        $bankAccounts = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        $totalTransactions = Transaction::where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        $currency = Currency::where('is_base', true)->first();
        $baseSymbol = $currency?->symbol ?? 'Bs.S';

        $mostUsedCurrency = $this->getMostUsedCurrency($companyId, $startDate, $endDate);
        $originalSymbol =  '$';

        // ✅ 4. Calcular días con datos en el período
        $daysWithData = Transaction::where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->select('date')
            ->distinct()
            ->count();

        $periodDescription = "Últimos {$daysWithData} días con datos (del {$startDate} al {$endDate})";

        return [
            Stat::make('Ingresos', $this->formatAmountWithBothCurrencies($totalIncomeOriginal, $totalIncomeConverted, $originalSymbol, $baseSymbol))
                ->description($periodDescription)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($this->getIncomeChart($companyId, $startDate, $endDate)),

            Stat::make('Egresos', $this->formatAmountWithBothCurrencies($totalExpenseOriginal, $totalExpenseConverted, $originalSymbol, $baseSymbol))
                ->description($periodDescription)
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->chart($this->getExpenseChart($companyId, $startDate, $endDate)),

            Stat::make('Balance Neto', $this->formatAmountWithBothCurrencies($netBalanceOriginal, $netBalanceConverted, $originalSymbol, $baseSymbol))
                ->description($netBalanceOriginal >= 0 ? 'Flujo positivo' : 'Flujo negativo')
                ->descriptionIcon($netBalanceOriginal >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netBalanceOriginal >= 0 ? 'success' : 'danger'),

            Stat::make('Transacciones', $totalTransactions)
                ->description($periodDescription)
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info')
                ->chart($this->getTransactionsChart($companyId, $startDate, $endDate)),
        ];
    }

    /**
     * ✅ Estadísticas globales (super_admin)
     */
    protected function getGlobalStats(): array
    {
        $lastTransactionDate = Transaction::orderBy('date', 'desc')->value('date');

        if (!$lastTransactionDate) {
            return $this->getEmptyStats();
        }

        $endDate = Carbon::parse($lastTransactionDate)->toDateString();
        $startDate = Carbon::parse($lastTransactionDate)->subDays(29)->toDateString();

        $incomeQuery = Transaction::where('type', 'income')
            ->whereBetween('date', [$startDate, $endDate]);

        $expenseQuery = Transaction::where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate]);

        $totalIncomeOriginal = $incomeQuery->sum('amount');
        $totalExpenseOriginal = $expenseQuery->sum('amount');
        $netBalanceOriginal = $totalIncomeOriginal - $totalExpenseOriginal;

        $totalIncomeConverted = $incomeQuery->sum('amount_converted');
        $totalExpenseConverted = $expenseQuery->sum('amount_converted');
        $netBalanceConverted = $totalIncomeConverted - $totalExpenseConverted;

        $totalCompanies = Company::where('is_active', true)->count();
        $totalTransactions = Transaction::whereBetween('date', [$startDate, $endDate])->count();

        $currency = Currency::where('is_base', true)->first();
        $baseSymbol = $currency?->symbol ?? 'Bs.S';

        $mostUsedCurrency = $this->getMostUsedCurrencyGlobal($startDate, $endDate);
        $originalSymbol = $mostUsedCurrency?->symbol ?? '$';

        $daysWithData = Transaction::whereBetween('date', [$startDate, $endDate])
            ->select('date')
            ->distinct()
            ->count();

        $periodDescription = "Últimos {$daysWithData} días con datos (del {$startDate} al {$endDate})";

        return [
            Stat::make('Ingresos globales', $this->formatAmountWithBothCurrencies($totalIncomeOriginal, $totalIncomeConverted, $originalSymbol, $baseSymbol))
                ->description($periodDescription)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Egresos globales', $this->formatAmountWithBothCurrencies($totalExpenseOriginal, $totalExpenseConverted, $originalSymbol, $baseSymbol))
                ->description($periodDescription)
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Empresas activas', $totalCompanies)
                ->description('Empresas activas en el sistema')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),

            Stat::make('Transacciones', $totalTransactions)
                ->description($periodDescription)
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
        ];
    }

    /**
     * Obtener la moneda más usada en las transacciones
     */
    protected function getMostUsedCurrency(int $companyId, string $startDate, string $endDate): ?\App\Models\Currency
    {
        $currencyId = Transaction::where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->select('currency_id')
            ->groupBy('currency_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('currency_id');

        return $currencyId ? \App\Models\Currency::find($currencyId) : null;
    }

    /**
     * Obtener la moneda más usada globalmente
     */
    protected function getMostUsedCurrencyGlobal(string $startDate, string $endDate): ?\App\Models\Currency
    {
        $currencyId = Transaction::whereBetween('date', [$startDate, $endDate])
            ->select('currency_id')
            ->groupBy('currency_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('currency_id');

        return $currencyId ? \App\Models\Currency::find($currencyId) : null;
    }

    /**
     * Formatear monto con ambas monedas
     */
    protected function formatAmountWithBothCurrencies(float $original, float $converted, string $originalSymbol, string $baseSymbol): string
    {
        $formattedOriginal = $baseSymbol . ' ' . number_format($original, 2, ',', '.') . PHP_EOL;
        $formattedConverted = $originalSymbol  . ' ' . number_format($converted, 2, ',', '.');

        return $formattedOriginal . "\n" . $formattedConverted;
    }

    /**
     * Obtener datos para el gráfico de ingresos
     */
    protected function getIncomeChart(int $companyId, string $startDate, string $endDate): array
    {
        $data = [];
        $period = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($period <= $end) {
            $date = $period->toDateString();
            $income = Transaction::where('company_id', $companyId)
                ->where('type', 'income')
                ->whereDate('date', $date)
                ->sum('amount_converted');
            $data[] = round($income, 0);
            $period->addDay();
        }

        // Reducir a máximo 7 puntos para el gráfico
        if (count($data) > 7) {
            $step = ceil(count($data) / 7);
            $data = array_filter($data, function ($key) use ($step) {
                return $key % $step === 0;
            }, ARRAY_FILTER_USE_KEY);
            $data = array_values($data);
        }

        return $data;
    }

    /**
     * Obtener datos para el gráfico de egresos
     */
    protected function getExpenseChart(int $companyId, string $startDate, string $endDate): array
    {
        $data = [];
        $period = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($period <= $end) {
            $date = $period->toDateString();
            $expense = Transaction::where('company_id', $companyId)
                ->where('type', 'expense')
                ->whereDate('date', $date)
                ->sum('amount_converted');
            $data[] = round($expense, 0);
            $period->addDay();
        }

        if (count($data) > 7) {
            $step = ceil(count($data) / 7);
            $data = array_filter($data, function ($key) use ($step) {
                return $key % $step === 0;
            }, ARRAY_FILTER_USE_KEY);
            $data = array_values($data);
        }

        return $data;
    }

    /**
     * Obtener datos para el gráfico de transacciones
     */
    protected function getTransactionsChart(int $companyId, string $startDate, string $endDate): array
    {
        $data = [];
        $period = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($period <= $end) {
            $date = $period->toDateString();
            $count = Transaction::where('company_id', $companyId)
                ->whereDate('date', $date)
                ->count();
            $data[] = $count;
            $period->addDay();
        }

        if (count($data) > 7) {
            $step = ceil(count($data) / 7);
            $data = array_filter($data, function ($key) use ($step) {
                return $key % $step === 0;
            }, ARRAY_FILTER_USE_KEY);
            $data = array_values($data);
        }

        return $data;
    }

    /**
     * Formatear moneda (legacy)
     */
    protected function formatCurrency(float $amount): string
    {
        $currency = Currency::where('is_base', true)->first();
        $symbol = $currency?->symbol ?? 'Bs.S';

        return $symbol . ' ' . number_format($amount, 2, ',', '.');
    }

    protected function getEmptyStats(): array
    {
        return [
            Stat::make('Sin datos', '—')
                ->description('No hay transacciones registradas')
                ->color('gray'),
        ];
    }
}
