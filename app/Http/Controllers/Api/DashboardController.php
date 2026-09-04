<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Models\Transaction;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected ReportService $reportService,
    ) {}

    /**
     * GET /api/dashboard/stats
     * Estadísticas para el dashboard
     */
    public function stats(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $summary = $this->reportService->getExecutiveSummary($companyId);

        $this->logActivity(
            action: 'view_dashboard',
            entityType: 'Dashboard',
            entityId: 0,
            note: json_encode([
                'section' => 'stats',
                'company_id' => $companyId,
            ])
        );

        return $this->successResponse($summary);
    }

    /**
     * GET /api/dashboard/trends
     * Tendencias mensuales
     */
    public function trends(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $months = $request->months ?? 12;
        $months = min(max((int) $months, 1), 24);

        $startDate = now()->subMonths($months)->startOfMonth()->toDateString();
        $endDate = now()->toDateString();

        $report = $this->reportService->getCashFlowReport($companyId, $startDate, $endDate);

        // Extraer datos de tendencia
        $dailyData = collect($report['daily_evolution'] ?? []);

        $result = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'months' => $months,
            ],
            'labels' => $dailyData->pluck('date_formatted'),
            'income_data' => $dailyData->pluck('income'),
            'expense_data' => $dailyData->pluck('expense'),
            'balance_data' => $dailyData->pluck('balance'),
            'cumulative_balance' => $dailyData->pluck('balance'),
            'totals' => [
                'income' => $report['totals']['income'] ?? 0,
                'expense' => $report['totals']['expense'] ?? 0,
                'net' => $report['totals']['net'] ?? 0,
            ],
        ];

        $this->logActivity(
            action: 'view_dashboard',
            entityType: 'Dashboard',
            entityId: 0,
            note: json_encode([
                'section' => 'trends',
                'company_id' => $companyId,
                'months' => $months,
            ])
        );

        return $this->successResponse($result);
    }

    /**
     * GET /api/dashboard/category-distribution
     * Distribución por categorías
     */
    public function categoryDistribution(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $incomeCategories = $this->reportService->getCategoryReport(
            companyId: $companyId,
            startDate: $startDate,
            endDate: $endDate,
            type: 'income'
        );

        $expenseCategories = $this->reportService->getCategoryReport(
            companyId: $companyId,
            startDate: $startDate,
            endDate: $endDate,
            type: 'expense'
        );

        $this->logActivity(
            action: 'view_dashboard',
            entityType: 'Dashboard',
            entityId: 0,
            note: json_encode([
                'section' => 'category_distribution',
                'company_id' => $companyId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ])
        );

        return $this->successResponse([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'income_categories' => $incomeCategories,
            'expense_categories' => $expenseCategories,
        ]);
    }

    /**
     * GET /api/dashboard/recent
     * Transacciones recientes
     */
    public function recent(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $limit = $request->limit ?? 10;
        $limit = min(max((int) $limit, 1), 50);

        $transactions = Transaction::where('company_id', $companyId)
            ->with(['account', 'category', 'bankAccount', 'currency'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date,
                    'type' => $transaction->type,
                    'type_label' => $transaction->type_label,
                    'amount' => $transaction->amount,
                    'amount_converted' => $transaction->amount_converted,
                    'currency' => $transaction->currency?->code,
                    'description' => $transaction->description,
                    'account' => $transaction->account?->name,
                    'category' => $transaction->category?->name,
                    'bank_account' => $transaction->bankAccount?->alias,
                ];
            });

        $this->logActivity(
            action: 'view_dashboard',
            entityType: 'Dashboard',
            entityId: 0,
            note: json_encode([
                'section' => 'recent',
                'company_id' => $companyId,
                'limit' => $limit,
            ])
        );

        return $this->successResponse([
            'transactions' => $transactions,
            'total' => $transactions->count(),
            'limit' => $limit,
        ]);
    }

    /**
     * GET /api/dashboard/quick-stats
     * Estadísticas rápidas (widgets del dashboard)
     */
    public function quickStats(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        // Transacciones del período
        $transactions = Transaction::where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate]);

        $totalIncome = (clone $transactions)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $transactions)->where('type', 'expense')->sum('amount');

        // Días transcurridos del mes
        $daysInMonth = now()->daysInMonth;
        $daysPassed = now()->day;

        // Promedios diarios
        $avgDailyIncome = $daysPassed > 0 ? $totalIncome / $daysPassed : 0;
        $avgDailyExpense = $daysPassed > 0 ? $totalExpense / $daysPassed : 0;

        // Proyección mensual
        $projectedIncome = $avgDailyIncome * $daysInMonth;
        $projectedExpense = $avgDailyExpense * $daysInMonth;

        // Últimas 5 transacciones
        $recent = Transaction::where('company_id', $companyId)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'date' => $t->date,
                    'type' => $t->type_label,
                    'amount' => $t->amount,
                    'description' => $t->description,
                ];
            });

        $this->logActivity(
            action: 'view_dashboard',
            entityType: 'Dashboard',
            entityId: 0,
            note: json_encode([
                'section' => 'quick_stats',
                'company_id' => $companyId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ])
        );

        return $this->successResponse([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_passed' => $daysPassed,
                'total_days' => $daysInMonth,
            ],
            'current' => [
                'income' => $totalIncome,
                'expense' => $totalExpense,
                'net' => $totalIncome - $totalExpense,
                'transaction_count' => $transactions->count(),
            ],
            'averages' => [
                'daily_income' => round($avgDailyIncome, 2),
                'daily_expense' => round($avgDailyExpense, 2),
                'daily_net' => round($avgDailyIncome - $avgDailyExpense, 2),
            ],
            'projections' => [
                'income' => round($projectedIncome, 2),
                'expense' => round($projectedExpense, 2),
                'net' => round($projectedIncome - $projectedExpense, 2),
            ],
            'recent_transactions' => $recent,
        ]);
    }

    /**
     * GET /api/dashboard/summary
     * Resumen completo del dashboard
     */
    public function summary(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        // Estadísticas ejecutivas
        $executiveSummary = $this->reportService->getExecutiveSummary($companyId);

        // Tendencias (últimos 6 meses)
        $startDate = now()->subMonths(6)->startOfMonth()->toDateString();
        $endDate = now()->toDateString();

        $cashFlow = $this->reportService->getCashFlowReport($companyId, $startDate, $endDate);

        // Agrupar por mes para tendencias
        $dailyData = collect($cashFlow['daily_evolution'] ?? []);
        $groupedByMonth = $dailyData->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item['date'])->format('M Y');
        });

        $result = [
            'executive_summary' => $executiveSummary,
            'monthly_trend' => [
                'labels' => $groupedByMonth->keys()->take(6),
                'income' => $groupedByMonth->map(function ($group) {
                    return $group->sum('income');
                })->values()->take(6),
                'expense' => $groupedByMonth->map(function ($group) {
                    return $group->sum('expense');
                })->values()->take(6),
            ],
            'recent_transactions' => Transaction::where('company_id', $companyId)
                ->orderBy('date', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'date' => $t->date,
                        'type' => $t->type_label,
                        'amount' => $t->amount,
                        'currency' => $t->currency?->code,
                        'description' => $t->description,
                        'account' => $t->account?->name,
                    ];
                }),
        ];

        $this->logActivity(
            action: 'view_dashboard',
            entityType: 'Dashboard',
            entityId: 0,
            note: json_encode([
                'section' => 'summary',
                'company_id' => $companyId,
            ])
        );

        return $this->successResponse($result);
    }

    /**
     * Resolver company_id (super_admin puede ver otras empresas)
     * ✅ Usa el método del Trait HasCompanyContext
     */
    protected function resolveCompanyId(Request $request): int
    {
        return $this->resolveCompanyIdFromRequest($request);
    }
}