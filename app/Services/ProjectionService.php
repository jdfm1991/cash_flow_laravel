<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\BankAccount;
use App\Enums\TransactionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProjectionService
{
    protected $scenarios = [
        'realistic' => [
            'label' => 'Realista',
            'income_factor' => 1.0,
            'expense_factor' => 1.0,
            'color' => '#4f46e5',
        ],
        'optimistic' => [
            'label' => 'Optimista',
            'income_factor' => 1.15,  // +15% ingresos
            'expense_factor' => 0.90, // -10% egresos
            'color' => '#10b981',
        ],
        'pessimistic' => [
            'label' => 'Pesimista',
            'income_factor' => 0.85,  // -15% ingresos
            'expense_factor' => 1.10, // +10% egresos
            'color' => '#ef4444',
        ],
    ];

    /**
     * Calcular proyección de flujo de caja
     */
    public function calculateProjection(
        int $companyId,
        int $months = 12,
        string $scenario = 'realistic'
    ): array {
        // ✅ 1. Obtener saldo inicial
        $startingBalance = $this->getStartingBalance($companyId);

        // ✅ 2. Obtener datos históricos (últimos 12 meses)
        $historicalData = $this->getHistoricalData($companyId, 12);

        // ✅ 3. Calcular promedios mensuales
        $averages = $this->calculateAverages($historicalData);

        // ✅ 4. Obtener factores del escenario
        $factors = $this->scenarios[$scenario] ?? $this->scenarios['realistic'];

        // ✅ 5. Generar proyección mes a mes
        $projection = $this->generateProjection(
            $startingBalance,
            $averages,
            $factors,
            $months
        );

        // ✅ 6. Calcular métricas
        $metrics = $this->calculateMetrics($projection);

        return [
            'scenario' => $scenario,
            'scenario_label' => $factors['label'],
            'scenario_color' => $factors['color'],
            'starting_balance' => $startingBalance,
            'projection' => $projection,
            'metrics' => $metrics,
            'alerts' => $this->generateAlerts($projection, $metrics),
            'summary' => $this->generateSummary($projection, $metrics),
            'historical_averages' => $averages,
        ];
    }

    /**
     * Obtener saldo inicial (suma de todas las cuentas)
     */
    protected function getStartingBalance(int $companyId): float
    {
        $accounts = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $totalBalance = 0;
        foreach ($accounts as $account) {
            $totalBalance += $account->calculateBalance();
        }

        return $totalBalance;
    }

    /**
     * Obtener datos históricos por mes
     */
    protected function getHistoricalData(int $companyId, int $months): array
    {
        $data = [];
        $endDate = Carbon::now()->endOfMonth();
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthDate = $startDate->copy()->addMonths($i);
            $monthStart = $monthDate->copy()->startOfMonth();
            $monthEnd = $monthDate->copy()->endOfMonth();

            $income = Transaction::where('company_id', $companyId)
                ->where('type', TransactionType::INCOME->value)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->sum('amount_converted');

            $expense = Transaction::where('company_id', $companyId)
                ->where('type', TransactionType::EXPENSE->value)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->sum('amount_converted');

            $data[] = [
                'month' => $monthDate->format('Y-m'),
                'month_name' => $monthDate->translatedFormat('F Y'),
                'income' => (float) $income,
                'expense' => (float) $expense,
                'net' => (float) ($income - $expense),
            ];
        }

        return $data;
    }

    /**
     * Calcular promedios mensuales
     */
    protected function calculateAverages(array $historicalData): array
    {
        $totalIncome = array_sum(array_column($historicalData, 'income'));
        $totalExpense = array_sum(array_column($historicalData, 'expense'));
        $count = count($historicalData);

        if ($count === 0) {
            return ['income' => 0, 'expense' => 0, 'net' => 0];
        }

        return [
            'income' => $totalIncome / $count,
            'expense' => $totalExpense / $count,
            'net' => ($totalIncome - $totalExpense) / $count,
        ];
    }

    /**
     * Generar proyección mes a mes
     */
    protected function generateProjection(
        float $startingBalance,
        array $averages,
        array $factors,
        int $months
    ): array {
        $projection = [];
        $balance = $startingBalance;
        $startDate = Carbon::now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthDate = $startDate->copy()->addMonths($i);

            // ✅ Aplicar factores del escenario
            $projectedIncome = $averages['income'] * $factors['income_factor'];
            $projectedExpense = $averages['expense'] * $factors['expense_factor'];
            $projectedNet = $projectedIncome - $projectedExpense;
            $balance += $projectedNet;

            $projection[] = [
                'month' => $monthDate->format('Y-m'),
                'month_name' => $monthDate->translatedFormat('F Y'),
                'income' => round($projectedIncome, 2),
                'expense' => round($projectedExpense, 2),
                'net' => round($projectedNet, 2),
                'balance' => round($balance, 2),
                'days_in_month' => $monthDate->daysInMonth,
            ];
        }

        return $projection;
    }

    /**
     * Calcular métricas
     */
    protected function calculateMetrics(array $projection): array
    {
        // ✅ 1. Días de cobertura (promedio de los últimos 3 meses)
        $lastMonths = array_slice($projection, -3);
        $avgExpense = array_sum(array_column($lastMonths, 'expense')) / 3;
        $dailyExpense = $avgExpense / 30;
        $currentBalance = end($projection)['balance'] ?? 0;
        $daysOfCoverage = $dailyExpense > 0 ? floor($currentBalance / $dailyExpense) : 999;

        // ✅ 2. Punto de equilibrio (mes en que se agota el saldo)
        $breakEvenMonth = null;
        $breakEvenIndex = null;
        foreach ($projection as $index => $month) {
            if ($month['balance'] <= 0) {
                $breakEvenMonth = $month['month_name'];
                $breakEvenIndex = $index;
                break;
            }
        }

        // ✅ 3. Tendencia de flujo (últimos 6 meses vs primeros 6 meses)
        $half = floor(count($projection) / 2);
        $firstHalf = array_slice($projection, 0, $half);
        $secondHalf = array_slice($projection, -$half);

        $firstAvgNet = array_sum(array_column($firstHalf, 'net')) / count($firstHalf);
        $secondAvgNet = array_sum(array_column($secondHalf, 'net')) / count($secondHalf);

        $flowTrend = $firstAvgNet > 0 ? ($secondAvgNet / $firstAvgNet) - 1 : 0;

        // ✅ 4. Mínimo y máximo de saldo
        $balances = array_column($projection, 'balance');
        $minBalance = min($balances);
        $maxBalance = max($balances);

        return [
            'days_of_coverage' => round($daysOfCoverage, 0),
            'break_even_month' => $breakEvenMonth,
            'break_even_index' => $breakEvenIndex,
            'flow_trend' => round($flowTrend * 100, 2),
            'flow_trend_label' => $flowTrend >= 0 ? '↑ Positiva' : '↓ Negativa',
            'flow_trend_color' => $flowTrend >= 0 ? 'text-success' : 'text-danger',
            'min_balance' => round($minBalance, 2),
            'max_balance' => round($maxBalance, 2),
            'avg_net' => round(array_sum(array_column($projection, 'net')) / count($projection), 2),
            'total_income' => round(array_sum(array_column($projection, 'income')), 2),
            'total_expense' => round(array_sum(array_column($projection, 'expense')), 2),
            'final_balance' => round(end($projection)['balance'] ?? 0, 2),
        ];
    }

    /**
     * Generar alertas
     */
    protected function generateAlerts(array $projection, array $metrics): array
    {
        $alerts = [];

        // ✅ Alerta de déficit (saldo < 0)
        foreach ($projection as $month) {
            if ($month['balance'] < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'month' => $month['month_name'],
                    'message' => "⚠️ Déficit proyectado en {$month['month_name']}: Bs.S " . number_format($month['balance'], 2),
                    'balance' => $month['balance'],
                ];
            }
        }

        // ✅ Alerta de bajo saldo (menos de 30 días de cobertura)
        if ($metrics['days_of_coverage'] < 30) {
            $alerts[] = [
                'type' => 'warning',
                'month' => 'Próximos meses',
                'message' => "⚠️ Baja cobertura: {$metrics['days_of_coverage']} días de efectivo disponible",
                'coverage' => $metrics['days_of_coverage'],
            ];
        }

        // ✅ Alerta si el punto de equilibrio está dentro del horizonte
        if ($metrics['break_even_month']) {
            $alerts[] = [
                'type' => 'danger',
                'month' => $metrics['break_even_month'],
                'message' => "⚠️ Punto de equilibrio en {$metrics['break_even_month']} - El saldo llegará a cero",
                'break_even' => true,
            ];
        }

        // ✅ Alerta de tendencia negativa
        if ($metrics['flow_trend'] < -10) {
            $alerts[] = [
                'type' => 'warning',
                'month' => 'Tendencia',
                'message' => "📉 Tendencia negativa: {$metrics['flow_trend']}% de caída en el flujo neto",
                'trend' => $metrics['flow_trend'],
            ];
        }

        return $alerts;
    }

    /**
     * Generar resumen ejecutivo
     */
    protected function generateSummary(array $projection, array $metrics): array
    {
        $firstMonth = $projection[0]['month_name'] ?? 'N/A';
        $lastMonth = end($projection)['month_name'] ?? 'N/A';

        return [
            'period' => "{$firstMonth} - {$lastMonth}",
            'total_months' => count($projection),
            'starting_balance' => $projection[0]['balance'] ?? 0,
            'final_balance' => $metrics['final_balance'],
            'total_income' => $metrics['total_income'],
            'total_expense' => $metrics['total_expense'],
            'net_change' => $metrics['final_balance'] - ($projection[0]['balance'] ?? 0),
            'days_of_coverage' => $metrics['days_of_coverage'],
            'break_even_month' => $metrics['break_even_month'],
            'flow_trend' => $metrics['flow_trend'],
            'flow_trend_label' => $metrics['flow_trend_label'],
        ];
    }
}