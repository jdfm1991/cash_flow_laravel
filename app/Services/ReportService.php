<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Company;
use App\Models\Category;
use App\Models\BankAccount;
use App\DTOs\ReportData;
use App\DTOs\ReportRow;
use App\Enums\TransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ReportService
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected BalanceService $balanceService,
    ) {}

    /**
     * Obtener reporte de flujo de caja
     */
    public function getCashFlowReport(
        int $companyId,
        string $startDate,
        string $endDate,
        ?int $categoryId = null,
        ?int $accountId = null
    ): array {
        // 1. Obtener transacciones del período
        $query = Transaction::with(['category', 'account', 'bankAccount', 'currency'])
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate]);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $transactions = $query->orderBy('date', 'asc')->get();

        // ✅ 2. Calcular totales por tipo (en moneda original y convertida)
        $totalIncome = $transactions->where('type', TransactionType::INCOME->value);
        $totalExpense = $transactions->where('type', TransactionType::EXPENSE->value);

        // Totales en moneda convertida (USD, EUR, etc.)
        $totalIncomeConverted = $totalIncome->sum('amount_converted');
        $totalExpenseConverted = $totalExpense->sum('amount_converted');

        // Totales en moneda original (suma de montos en su moneda original)
        $totalIncomeOriginal = $totalIncome->sum('amount');
        $totalExpenseOriginal = $totalExpense->sum('amount');

        // 3. Calcular totales por categoría (con ambos montos)
        $incomeByCategory = $transactions
            ->where('type', TransactionType::INCOME->value)
            ->groupBy('category_id')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'category' => $first->category?->name ?? 'Sin categoría',
                    'total_original' => $group->sum('amount'),
                    'total_converted' => $group->sum('amount_converted'),
                    'currency' => $first->currency?->code ?? 'N/A',
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('total_converted');

        $expenseByCategory = $transactions
            ->where('type', TransactionType::EXPENSE->value)
            ->groupBy('category_id')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'category' => $first->category?->name ?? 'Sin categoría',
                    'total_original' => $group->sum('amount'),
                    'total_converted' => $group->sum('amount_converted'),
                    'currency' => $first->currency?->code ?? 'N/A',
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('total_converted');

        // 4. Calcular evolución diaria (con ambos montos)
        $dailyEvolution = $this->calculateDailyEvolutionWithCurrency($transactions, $startDate, $endDate);

        // 5. Calcular resumen de cuentas
        $accountSummary = $this->getAccountSummary($companyId, $startDate, $endDate);

        // ✅ 6. Calcular resumen de cuentas bancarias (para el final del reporte)
        $bankAccountSummary = $this->getBankAccountSummary($companyId, $startDate, $endDate);

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'days' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1,
            ],
            'totals' => [
                // ✅ Totales en moneda original
                'income_original' => $totalIncomeOriginal,
                'expense_original' => $totalExpenseOriginal,
                'net_original' => $totalIncomeOriginal - $totalExpenseOriginal,

                // ✅ Totales en moneda convertida (VES)
                'income_converted' => $totalIncomeConverted,
                'expense_converted' => $totalExpenseConverted,
                'net_converted' => $totalIncomeConverted - $totalExpenseConverted,

                'transaction_count' => $transactions->count(),
            ],
            'income_by_category' => $incomeByCategory,
            'expense_by_category' => $expenseByCategory,
            'daily_evolution' => $dailyEvolution,
            'account_summary' => $accountSummary,
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date,
                    'type' => $transaction->type,
                    'type_label' => $transaction->type_label,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency?->code,
                    'amount_converted' => $transaction->amount_converted,
                    'exchange_rate' => $transaction->exchange_rate,
                    'description' => $transaction->description,
                    'account' => $transaction->account?->name,
                    'category' => $transaction->category?->name,
                    'bank_account' => $transaction->bankAccount?->alias,
                ];
            }),
            'bank_account_summary' => $bankAccountSummary,
        ];
    }

    /**
     * Calcular evolución diaria con ambos montos (original y convertido)
     */
    protected function calculateDailyEvolutionWithCurrency($transactions, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $dailyData = [];
        $runningBalanceOriginal = 0;
        $runningBalanceConverted = 0;

        // Agrupar transacciones por día
        $groupedByDay = $transactions->groupBy(function ($item) {
            return $item->date;
        });

        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $dateStr = $date->toDateString();
            $dayTransactions = $groupedByDay->get($dateStr, collect());

            $dayIncomeOriginal = $dayTransactions
                ->where('type', TransactionType::INCOME->value)
                ->sum('amount');
            $dayExpenseOriginal = $dayTransactions
                ->where('type', TransactionType::EXPENSE->value)
                ->sum('amount');

            $dayIncomeConverted = $dayTransactions
                ->where('type', TransactionType::INCOME->value)
                ->sum('amount_converted');
            $dayExpenseConverted = $dayTransactions
                ->where('type', TransactionType::EXPENSE->value)
                ->sum('amount_converted');

            $runningBalanceOriginal += $dayIncomeOriginal - $dayExpenseOriginal;
            $runningBalanceConverted += $dayIncomeConverted - $dayExpenseConverted;

            $dailyData[] = [
                'date' => $dateStr,
                'date_formatted' => $date->translatedFormat('d/m/Y'),
                'day_of_week' => $date->translatedFormat('l'),

                // Montos originales
                'income_original' => $dayIncomeOriginal,
                'expense_original' => $dayExpenseOriginal,
                'net_original' => $dayIncomeOriginal - $dayExpenseOriginal,
                'balance_original' => $runningBalanceOriginal,

                // Montos convertidos
                'income_converted' => $dayIncomeConverted,
                'expense_converted' => $dayExpenseConverted,
                'net_converted' => $dayIncomeConverted - $dayExpenseConverted,
                'balance_converted' => $runningBalanceConverted,

                'transactions' => $dayTransactions->count(),
            ];
        }

        return $dailyData;
    }

    /**
     * Obtener reporte por categorías
     */
    public function getCategoryReport(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $type = 'all'  // ✅ Valor por defecto
    ): array {
        // Si type es null, usar 'all'
        $type = $type ?? 'all';

        $query = Transaction::with(['category'])
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate]);

        // Filtrar por tipo si no es 'all'
        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $transactions = $query->get();

        $grouped = $transactions->groupBy('category_id');
        $total = $transactions->sum('amount_converted');

        $result = [];
        foreach ($grouped as $categoryId => $items) {
            $category = $items->first()->category;
            $categoryTotal = $items->sum('amount_converted');

            $result[] = [
                'category_id' => $categoryId,
                'category_name' => $category?->name ?? 'Sin categoría',
                'category_color' => $category?->color ?? '#6c757d',
                'category_icon' => $category?->icon ?? 'bi-tag',
                'total' => $categoryTotal,
                'count' => $items->count(),
                'percentage' => $total > 0 ? round(($categoryTotal / $total) * 100, 2) : 0,
            ];
        }

        usort($result, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return $result;
    }
    /**
     * Obtener resumen mensual
     */
    public function getMonthlySummary(int $companyId, int $year): array
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::create($year, $month, 1)->toDateString();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $income = Transaction::where('company_id', $companyId)
                ->where('type', TransactionType::INCOME->value)
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount_converted');

            $expense = Transaction::where('company_id', $companyId)
                ->where('type', TransactionType::EXPENSE->value)
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount_converted');

            $months[] = [
                'month' => $month,
                'month_name' => Carbon::create($year, $month, 1)->translatedFormat('F'),
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        }

        return $months;
    }

    /**
     * Obtener resumen de cuentas
     */
    public function getAccountSummary(int $companyId, string $startDate, string $endDate): array
    {
        $accounts = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['bank', 'currency'])
            ->get();

        $summary = [];

        foreach ($accounts as $account) {
            $totalIncome = Transaction::where('company_id', $companyId)
                ->where('bank_account_id', $account->id)
                ->where('type', TransactionType::INCOME->value)
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount_converted');

            $totalExpense = Transaction::where('company_id', $companyId)
                ->where('bank_account_id', $account->id)
                ->where('type', TransactionType::EXPENSE->value)
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount_converted');

            $initialBalance = $this->balanceService->getBalanceOnDate($account, $startDate);
            $finalBalance = $this->balanceService->getBalanceOnDate($account, $endDate);

            $summary[] = [
                'account_id' => $account->id,
                'account_alias' => $account->alias,
                'account_number' => $account->account_number,
                'bank_name' => $account->bank?->name ?? 'N/A',
                'currency' => $account->currency?->code ?? 'N/A',
                'initial_balance' => $initialBalance,
                'income' => $totalIncome,
                'expense' => $totalExpense,
                'final_balance' => $finalBalance,
                'net_change' => $finalBalance - $initialBalance,
            ];
        }

        return $summary;
    }

    /**
     * Obtener resumen ejecutivo (dashboard)
     */
    public function getExecutiveSummary(int $companyId): array
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $startOfYear = $today->copy()->startOfYear();

        // Mes actual
        $monthIncome = Transaction::where('company_id', $companyId)
            ->where('type', TransactionType::INCOME->value)
            ->whereBetween('date', [$startOfMonth, $today])
            ->sum('amount_converted');

        $monthExpense = Transaction::where('company_id', $companyId)
            ->where('type', TransactionType::EXPENSE->value)
            ->whereBetween('date', [$startOfMonth, $today])
            ->sum('amount_converted');

        // Año actual
        $yearIncome = Transaction::where('company_id', $companyId)
            ->where('type', TransactionType::INCOME->value)
            ->whereBetween('date', [$startOfYear, $today])
            ->sum('amount_converted');

        $yearExpense = Transaction::where('company_id', $companyId)
            ->where('type', TransactionType::EXPENSE->value)
            ->whereBetween('date', [$startOfYear, $today])
            ->sum('amount_converted');

        // Saldo total de cuentas
        $totalBalance = $this->balanceService->getCompanyTotalBalance($companyId);

        // Últimas transacciones
        $recentTransactions = Transaction::with(['account', 'category', 'bankAccount'])
            ->where('company_id', $companyId)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date,
                    'type' => $transaction->type,
                    'type_label' => $transaction->type_label,
                    'amount' => $transaction->amount,
                    'amount_converted' => $transaction->amount_converted,
                    'description' => $transaction->description,
                    'account' => $transaction->account?->name,
                    'category' => $transaction->category?->name,
                ];
            });

        return [
            'period' => [
                'today' => $today->toDateString(),
                'month' => $startOfMonth->toDateString(),
                'year' => $startOfYear->toDateString(),
            ],
            'month' => [
                'income' => $monthIncome,
                'expense' => $monthExpense,
                'net' => $monthIncome - $monthExpense,
                'days' => $today->diffInDays($startOfMonth) + 1,
            ],
            'year' => [
                'income' => $yearIncome,
                'expense' => $yearExpense,
                'net' => $yearIncome - $yearExpense,
            ],
            'total_balance' => $totalBalance,
            'recent_transactions' => $recentTransactions,
            'account_count' => BankAccount::where('company_id', $companyId)
                ->where('is_active', true)
                ->count(),
            'transaction_count_month' => Transaction::where('company_id', $companyId)
                ->whereBetween('date', [$startOfMonth, $today])
                ->count(),
        ];
    }

    /**
     * Obtener balance comparativo (mes actual vs mes anterior)
     */
    public function getComparativeBalance(int $companyId): array
    {
        $today = Carbon::today();
        $currentMonthStart = $today->copy()->startOfMonth();
        $previousMonthStart = $today->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $today->copy()->subMonth()->endOfMonth();

        // Mes actual
        $currentIncome = Transaction::where('company_id', $companyId)
            ->where('type', TransactionType::INCOME->value)
            ->whereBetween('date', [$currentMonthStart, $today])
            ->sum('amount_converted');

        $currentExpense = Transaction::where('company_id', $companyId)
            ->where('type', TransactionType::EXPENSE->value)
            ->whereBetween('date', [$currentMonthStart, $today])
            ->sum('amount_converted');

        // Mes anterior
        $previousIncome = Transaction::where('company_id', $companyId)
            ->where('type', TransactionType::INCOME->value)
            ->whereBetween('date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount_converted');

        $previousExpense = Transaction::where('company_id', $companyId)
            ->where('type', TransactionType::EXPENSE->value)
            ->whereBetween('date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount_converted');

        // Calcular porcentajes de cambio
        $incomeChange = $previousIncome > 0
            ? round((($currentIncome - $previousIncome) / $previousIncome) * 100, 2)
            : 0;

        $expenseChange = $previousExpense > 0
            ? round((($currentExpense - $previousExpense) / $previousExpense) * 100, 2)
            : 0;

        return [
            'current_month' => [
                'start' => $currentMonthStart->toDateString(),
                'end' => $today->toDateString(),
                'income' => $currentIncome,
                'expense' => $currentExpense,
                'net' => $currentIncome - $currentExpense,
            ],
            'previous_month' => [
                'start' => $previousMonthStart->toDateString(),
                'end' => $previousMonthEnd->toDateString(),
                'income' => $previousIncome,
                'expense' => $previousExpense,
                'net' => $previousIncome - $previousExpense,
            ],
            'comparison' => [
                'income' => [
                    'absolute' => $currentIncome - $previousIncome,
                    'percentage' => $incomeChange,
                    'direction' => $incomeChange >= 0 ? 'up' : 'down',
                ],
                'expense' => [
                    'absolute' => $currentExpense - $previousExpense,
                    'percentage' => $expenseChange,
                    'direction' => $expenseChange >= 0 ? 'up' : 'down',
                ],
            ],
        ];
    }

    /**
     * Obtener reporte de transacciones
     */
    public function getTransactionReport(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $type = null,
        ?int $accountId = null,
        ?int $categoryId = null,
        ?int $bankAccountId = null
    ): array {
        $query = Transaction::with(['account', 'category', 'bankAccount', 'currency'])
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate]);

        if ($type) {
            $query->where('type', $type);
        }

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        $transactions = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // ✅ Obtener totales en ambas monedas
        $totalTransactions = $transactions;
        $incomeTransactions = $transactions->where('type', 'income');
        $expenseTransactions = $transactions->where('type', 'expense');

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => [
                // ✅ Totales en moneda convertida (VES)
                'total' => $totalTransactions->sum('amount_converted'),
                'income' => $incomeTransactions->sum('amount_converted'),
                'expense' => $expenseTransactions->sum('amount_converted'),

                // ✅ Totales en moneda original
                'total_original' => $totalTransactions->sum('amount'),
                'income_original' => $incomeTransactions->sum('amount'),
                'expense_original' => $expenseTransactions->sum('amount'),

                'count' => $transactions->count(),
            ],
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date,
                    'type' => $transaction->type,
                    'type_label' => $transaction->type_label,
                    'amount' => $transaction->amount,
                    'amount_converted' => $transaction->amount_converted,
                    'currency' => $transaction->currency?->code,
                    'exchange_rate' => $transaction->exchange_rate,
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'account' => $transaction->account?->name,
                    'account_id' => $transaction->account_id,
                    'category' => $transaction->category?->name,
                    'category_id' => $transaction->category_id,
                    'bank_account' => $transaction->bankAccount?->alias,
                    'bank_account_id' => $transaction->bank_account_id,
                ];
            }),
        ];
    }

    /**
     * Obtener evolución diaria
     */
    public function getDailyEvolution(
        int $companyId,
        string $startDate,
        string $endDate
    ): array {
        $transactions = Transaction::where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();

        return $this->calculateDailyEvolution($transactions, $startDate, $endDate);
    }

    /**
     * Calcular evolución diaria a partir de transacciones
     * Este es el método que hace el trabajo real
     */
    protected function calculateDailyEvolution($transactions, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $dailyData = [];
        $runningBalance = 0;

        // Agrupar transacciones por día
        $groupedByDay = $transactions->groupBy(function ($item) {
            return $item->date;
        });

        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $dateStr = $date->toDateString();
            $dayTransactions = $groupedByDay->get($dateStr, collect());

            $dayIncome = $dayTransactions
                ->where('type', TransactionType::INCOME->value)
                ->sum('amount_converted');

            $dayExpense = $dayTransactions
                ->where('type', TransactionType::EXPENSE->value)
                ->sum('amount_converted');

            $runningBalance += $dayIncome - $dayExpense;

            $dailyData[] = [
                'date' => $dateStr,
                'date_formatted' => $date->translatedFormat('d/m/Y'),
                'day_of_week' => $date->translatedFormat('l'),
                'income' => $dayIncome,
                'expense' => $dayExpense,
                'net' => $dayIncome - $dayExpense,
                'balance' => $runningBalance,
                'transactions' => $dayTransactions->count(),
            ];
        }

        return $dailyData;
    }

    /**
     * Obtener comparación mensual
     */
    public function getMonthlyComparison(
        int $companyId,
        ?int $year = null,
        int $months = 12
    ): array {
        $year = $year ?? (int) date('Y');
        $result = [];
        $currentMonth = (int) date('m');

        for ($i = 0; $i < $months; $i++) {
            $month = $currentMonth - $i;
            $yearOffset = 0;

            if ($month <= 0) {
                $month += 12;
                $yearOffset = 1;
            }

            $targetYear = $year - $yearOffset;

            $startDate = Carbon::create($targetYear, $month, 1)->toDateString();
            $endDate = Carbon::create($targetYear, $month, 1)->endOfMonth()->toDateString();

            $incomeQuery = Transaction::where('company_id', $companyId)
                ->where('type', TransactionType::INCOME->value)
                ->whereBetween('date', [$startDate, $endDate]);

            $expenseQuery = Transaction::where('company_id', $companyId)
                ->where('type', TransactionType::EXPENSE->value)
                ->whereBetween('date', [$startDate, $endDate]);

            $incomeConverted = $incomeQuery->sum('amount_converted');
            $incomeOriginal = $incomeQuery->sum('amount');

            $expenseConverted = $expenseQuery->sum('amount_converted');
            $expenseOriginal = $expenseQuery->sum('amount');

            // ✅ Formato corto para el gráfico: "sep-2025"
            $monthShortName = Carbon::create($targetYear, $month, 1)->locale('es')->translatedFormat('M');
            $monthShortYear = Carbon::create($targetYear, $month, 1)->format('Y');
            $monthDisplayShort = strtolower($monthShortName) . '-' . $monthShortYear;

            $result[] = [
                'month' => $month,
                'year' => $targetYear,
                'month_name' => Carbon::create($targetYear, $month, 1)->locale('es')->translatedFormat('F Y'),
                'month_name_short' => $monthDisplayShort,  // ✅ Nuevo campo para gráficos
                'start_date' => $startDate,
                'end_date' => $endDate,

                'income' => $incomeConverted,
                'expense' => $expenseConverted,
                'net' => $incomeConverted - $expenseConverted,

                'income_original' => $incomeOriginal,
                'expense_original' => $expenseOriginal,
                'net_original' => $incomeOriginal - $expenseOriginal,
            ];
        }

        usort($result, function ($a, $b) {
            return $b['year'] <=> $a['year'] ?: $b['month'] <=> $a['month'];
        });

        return $result;
    }

    /**
     * Obtener resumen anual
     */
    public function getYearlySummary(
        int $companyId,
        ?int $startYear = null,
        ?int $endYear = null
    ): array {
        $currentYear = (int) date('Y');
        $startYear = $startYear ?? ($currentYear - 5);
        $endYear = $endYear ?? $currentYear;

        $result = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $startDate = Carbon::create($year, 1, 1)->toDateString();
            $endDate = Carbon::create($year, 12, 31)->toDateString();

            $incomeQuery = Transaction::where('company_id', $companyId)
                ->where('type', TransactionType::INCOME->value)
                ->whereBetween('date', [$startDate, $endDate]);

            $expenseQuery = Transaction::where('company_id', $companyId)
                ->where('type', TransactionType::EXPENSE->value)
                ->whereBetween('date', [$startDate, $endDate]);

            // ✅ Montos convertidos (VES)
            $income = $incomeQuery->sum('amount_converted');
            $expense = $expenseQuery->sum('amount_converted');
            $net = $income - $expense;

            // ✅ Montos originales
            $incomeOriginal = $incomeQuery->sum('amount');
            $expenseOriginal = $expenseQuery->sum('amount');
            $netOriginal = $incomeOriginal - $expenseOriginal;

            // ✅ Conteos
            $incomeCount = $incomeQuery->count();
            $expenseCount = $expenseQuery->count();

            $result[] = [
                'year' => $year,
                'start_date' => $startDate,
                'end_date' => $endDate,
                // ✅ Convertidos
                'income' => $income,
                'expense' => $expense,
                'net' => $net,
                // ✅ Originales
                'income_original' => $incomeOriginal,
                'expense_original' => $expenseOriginal,
                'net_original' => $netOriginal,
                'income_count' => $incomeCount,
                'expense_count' => $expenseCount,
            ];
        }

        // ✅ Ordenar de más reciente a más antiguo
        usort($result, function ($a, $b) {
            return $b['year'] <=> $a['year'];
        });

        return $result;
    }


    /**
     * Preparar datos para la vista de Monthly Comparison
     */
    public function getMonthlyComparisonData(int $companyId, ?int $year = null, int $months = 12): array
    {
        $data = $this->getMonthlyComparison($companyId, $year, $months);

        $labels = [];
        $incomeData = [];
        $expenseData = [];
        $netData = [];
        $totals = [
            'income' => 0,
            'expense' => 0,
            'net' => 0,
        ];

        foreach ($data as $month) {
            $labels[] = $month['month_name'];
            $incomeData[] = $month['income'];
            $expenseData[] = $month['expense'];
            $netData[] = $month['net'];
            $totals['income'] += $month['income'];
            $totals['expense'] += $month['expense'];
            $totals['net'] += $month['net'];
        }

        return [
            'months' => $data,
            'labels' => $labels,
            'incomeData' => $incomeData,
            'expenseData' => $expenseData,
            'netData' => $netData,
            'totals' => $totals,
            'startYear' => $year ?? date('Y'),
            'endYear' => $year ?? date('Y'),
        ];
    }

    /**
     * Preparar datos para la vista de Yearly Summary
     */
    public function getYearlySummaryData(int $companyId, ?int $startYear = null, ?int $endYear = null): array
    {
        $data = $this->getYearlySummary($companyId, $startYear, $endYear);

        $labels = [];
        $incomeData = [];
        $expenseData = [];
        $totals = [
            'income' => 0,
            'expense' => 0,
            'net' => 0,
        ];

        foreach ($data as $year) {
            $labels[] = $year['year'];
            $incomeData[] = $year['income'];
            $expenseData[] = $year['expense'];
            $totals['income'] += $year['income'];
            $totals['expense'] += $year['expense'];
            $totals['net'] += $year['net'];
        }

        return [
            'years' => $data,
            'labels' => $labels,
            'incomeData' => $incomeData,
            'expenseData' => $expenseData,
            'totals' => $totals,
        ];
    }

    /**
     * Obtener resumen de transacciones agrupadas por categoría y cuenta
     */
    public function getCategorySummary(int $companyId, string $startDate, string $endDate): array
    {
        $transactions = Transaction::with(['category', 'account'])
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $incomeByCategory = [];
        $expenseByCategory = [];

        foreach ($transactions as $transaction) {
            $categoryName = $transaction->category?->name ?? 'Sin categoría';
            $accountName = $transaction->account?->name ?? 'Sin cuenta';
            $type = $transaction->type;

            // Determinar si es ingreso o egreso por el tipo de categoría
            $isIncome = $transaction->category?->type === 'income' || $transaction->type === 'income';
            $target = $isIncome ? $incomeByCategory : $expenseByCategory;

            if (!isset($target[$categoryName])) {
                $target[$categoryName] = [
                    'category' => $categoryName,
                    'total' => 0,
                    'accounts' => [],
                    'transaction_count' => 0,
                ];
            }

            if (!isset($target[$categoryName]['accounts'][$accountName])) {
                $target[$categoryName]['accounts'][$accountName] = [
                    'account' => $accountName,
                    'total' => 0,
                    'transaction_count' => 0,
                ];
            }

            $target[$categoryName]['total'] += $transaction->amount_converted;
            $target[$categoryName]['accounts'][$accountName]['total'] += $transaction->amount_converted;
            $target[$categoryName]['transaction_count']++;
            $target[$categoryName]['accounts'][$accountName]['transaction_count']++;

            if ($isIncome) {
                $incomeByCategory = $target;
            } else {
                $expenseByCategory = $target;
            }
        }

        // Ordenar por total (descendente)
        usort($incomeByCategory, fn($a, $b) => $b['total'] <=> $a['total']);
        usort($expenseByCategory, fn($a, $b) => $b['total'] <=> $a['total']);

        // Ordenar cuentas dentro de cada categoría
        foreach ($incomeByCategory as &$category) {
            usort($category['accounts'], fn($a, $b) => $b['total'] <=> $a['total']);
        }
        foreach ($expenseByCategory as &$category) {
            usort($category['accounts'], fn($a, $b) => $b['total'] <=> $a['total']);
        }

        return [
            'income' => $incomeByCategory,
            'expense' => $expenseByCategory,
            'totals' => [
                'income' => array_sum(array_column($incomeByCategory, 'total')),
                'expense' => array_sum(array_column($expenseByCategory, 'total')),
            ],
        ];
    }

    /**
     * Obtener resumen de transacciones agrupadas por mes, categoría y cuenta
     */
    public function getMonthlyCategorySummary(int $companyId, string $startDate, string $endDate): array
    {
        $transactions = Transaction::with(['category', 'account', 'currency'])
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();

        $months = [];

        foreach ($transactions as $transaction) {
            $monthKey = date('Y-m', strtotime($transaction->date));
            $monthName = Carbon::parse($transaction->date)->locale('es')->translatedFormat('F Y');
            $categoryName = $transaction->category?->name ?? 'Sin categoría';
            $accountName = $transaction->account?->name ?? 'Sin cuenta';
            $isIncome = $transaction->category?->type === 'income' || $transaction->type === 'income';

            // ✅ Obtener el código de la moneda original
            $currencyCode = $transaction->currency?->code ?? 'USD';

            // Inicializar mes si no existe
            if (!isset($months[$monthKey])) {
                $months[$monthKey] = [
                    'month_key' => $monthKey,
                    'month_name' => $monthName,
                    'currency' => $currencyCode, // ✅ Moneda del mes (la primera transacción)
                    'income' => [],
                    'expense' => [],
                    'totals' => [
                        'income' => 0,
                        'income_original' => 0,
                        'expense' => 0,
                        'expense_original' => 0,
                        'net' => 0,
                        'net_original' => 0,
                        'transaction_count' => 0,
                    ],
                ];
            }

            // Determinar el array de destino
            $target = $isIncome ? 'income' : 'expense';

            // Inicializar categoría
            if (!isset($months[$monthKey][$target][$categoryName])) {
                $months[$monthKey][$target][$categoryName] = [
                    'category' => $categoryName,
                    'total' => 0,
                    'total_original' => 0,
                    'currency' => $currencyCode,
                    'accounts' => [],
                    'transaction_count' => 0,
                ];
            }

            // Inicializar cuenta
            if (!isset($months[$monthKey][$target][$categoryName]['accounts'][$accountName])) {
                $months[$monthKey][$target][$categoryName]['accounts'][$accountName] = [
                    'account' => $accountName,
                    'total' => 0,
                    'total_original' => 0,
                    'transaction_count' => 0,
                ];
            }

            // ✅ Acumular valores
            $amountOriginal = $transaction->amount ?? 0;
            $amountConverted = $transaction->amount_converted ?? 0;

            // Categoría
            $months[$monthKey][$target][$categoryName]['total'] += $amountConverted;
            $months[$monthKey][$target][$categoryName]['total_original'] += $amountOriginal;
            $months[$monthKey][$target][$categoryName]['transaction_count']++;

            // Cuenta
            $months[$monthKey][$target][$categoryName]['accounts'][$accountName]['total'] += $amountConverted;
            $months[$monthKey][$target][$categoryName]['accounts'][$accountName]['total_original'] += $amountOriginal;
            $months[$monthKey][$target][$categoryName]['accounts'][$accountName]['transaction_count']++;

            // Totales del mes
            if ($isIncome) {
                $months[$monthKey]['totals']['income'] += $amountConverted;
                $months[$monthKey]['totals']['income_original'] += $amountOriginal;
            } else {
                $months[$monthKey]['totals']['expense'] += $amountConverted;
                $months[$monthKey]['totals']['expense_original'] += $amountOriginal;
            }
            $months[$monthKey]['totals']['transaction_count']++;
        }

        // Ordenar meses cronológicamente
        ksort($months);

        // Ordenar categorías y calcular netos
        foreach ($months as &$month) {
            foreach (['income', 'expense'] as $type) {
                if (!empty($month[$type])) {
                    usort($month[$type], fn($a, $b) => $b['total'] <=> $a['total']);
                    foreach ($month[$type] as &$category) {
                        usort($category['accounts'], fn($a, $b) => $b['total'] <=> $a['total']);
                    }
                }
            }
            // Calcular netos
            $month['totals']['net'] = $month['totals']['income'] - $month['totals']['expense'];
            $month['totals']['net_original'] = $month['totals']['income_original'] - $month['totals']['expense_original'];
        }

        return array_values($months);
    }

    /**
     * Obtener resumen de cuentas bancarias con saldos
     */
    public function getBankAccountSummary(int $companyId, string $startDate, string $endDate): array
    {
        $accounts = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['bank', 'currency'])
            ->get();

        $summary = [];

        foreach ($accounts as $account) {
            // ✅ Obtener transacciones del período para esta cuenta
            $transactions = Transaction::where('company_id', $companyId)
                ->where('bank_account_id', $account->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            // ✅ Calcular ingresos y egresos en moneda original
            $totalIncome = $transactions->where('type', TransactionType::INCOME->value)->sum('amount');
            $totalExpense = $transactions->where('type', TransactionType::EXPENSE->value)->sum('amount');

            // ✅ Calcular ingresos y egresos en moneda convertida
            $totalIncomeConverted = $transactions->where('type', TransactionType::INCOME->value)->sum('amount_converted');
            $totalExpenseConverted = $transactions->where('type', TransactionType::EXPENSE->value)->sum('amount_converted');

            // ✅ Obtener saldos usando BalanceService
            $initialBalance = $this->balanceService->getBalanceOnDate($account, $startDate);
            $finalBalance = $this->balanceService->getBalanceOnDate($account, $endDate);

            // ✅ Calcular saldo en moneda convertida (VES)
            $initialBalanceConverted = $initialBalance * ($account->currency?->exchange_rate ?? 1);
            $finalBalanceConverted = $finalBalance * ($account->currency?->exchange_rate ?? 1);

            $summary[] = [
                'account_id' => $account->id,
                'account_alias' => $account->alias,
                'account_number' => $account->account_number,
                'bank_name' => $account->bank?->name ?? 'N/A',
                'currency' => $account->currency?->code ?? 'N/A',
                'opening_balance' => $account->opening_balance,
                'initial_balance' => $initialBalance,
                'initial_balance_converted' => $initialBalanceConverted,
                'income' => $totalIncome,
                'income_converted' => $totalIncomeConverted,
                'expense' => $totalExpense,
                'expense_converted' => $totalExpenseConverted,
                'final_balance' => $finalBalance,
                'final_balance_converted' => $finalBalanceConverted,
                'net_change' => $finalBalance - $initialBalance,
                'net_change_converted' => $finalBalanceConverted - $initialBalanceConverted,
                'transaction_count' => $transactions->count(),
            ];
        }

        // Ordenar por alias
        usort($summary, fn($a, $b) => strcmp($a['account_alias'], $b['account_alias']));

        return $summary;
    }
}
