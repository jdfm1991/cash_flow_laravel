<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\CashFlowRequest;
use App\Http\Requests\Report\TransactionReportRequest;
use App\Http\Requests\Report\CategoryReportRequest;
use App\Http\Requests\Report\AccountReportRequest;
use App\Http\Requests\Report\MonthlyComparisonRequest;
use App\Http\Requests\Report\YearlySummaryRequest;
use App\Services\ReportService;
use App\Services\ExportService;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\Request;
// ✅ Eliminar JsonResponse para permitir BinaryFileResponse

class ReportController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected ReportService $reportService,
        protected ExportService $exportService,
    ) {}

    /**
     * GET /api/reports/cash-flow
     * Reporte de flujo de caja
     */
    public function cashFlow(CashFlowRequest $request)  // ✅ Sin type hint
    {
        $companyId = $this->getCurrentCompanyId();

        $result = $this->reportService->getCashFlowReport(
            companyId: $companyId,
            startDate: $request->start_date,
            endDate: $request->end_date,
            categoryId: $request->category_id,
            accountId: $request->account_id,
        );

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'cash_flow',
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'filters' => $request->only(['category_id', 'account_id']),
            ])
        );

        return $this->successResponse($result);
    }

    /**
     * GET /api/reports/executive
     * Resumen ejecutivo para dashboard
     */
    public function executiveSummary(Request $request)  // ✅ Sin type hint
    {
        $companyId = $this->getCurrentCompanyId();

        $result = $this->reportService->getExecutiveSummary($companyId);

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'executive_summary',
            ])
        );

        return $this->successResponse($result);
    }

    /**
     * GET /api/reports/comparative
     * Balance comparativo mes actual vs mes anterior
     */
    public function comparativeBalance(Request $request)  // ✅ Sin type hint
    {
        $companyId = $this->getCurrentCompanyId();

        $result = $this->reportService->getComparativeBalance($companyId);

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'comparative_balance',
            ])
        );

        return $this->successResponse($result);
    }

    /**
     * GET /api/reports/categories
     * Reporte por categorías
     */
    public function categoryReport(CategoryReportRequest $request)  // ✅ Sin type hint
    {
        $companyId = $this->getCurrentCompanyId();
        $format = $request->format ?? 'json';

        $type = $request->type ?? 'all';

        $data = $this->reportService->getCategoryReport(
            companyId: $companyId,
            startDate: $request->start_date,
            endDate: $request->end_date,
            type: $type,
        );

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'category_report',
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'type' => $type,
                'format' => $format,
            ])
        );

        if (in_array($format, ['excel', 'pdf'])) {
            return $this->exportService->exportCategories($data, $format);
        }

        return $this->successResponse($data);
    }

    /**
     * GET /api/reports/transactions
     * Reporte detallado de transacciones
     */
    public function transactionReport(TransactionReportRequest $request)  // ✅ Sin type hint
    {
        $companyId = $this->getCurrentCompanyId();
        $format = $request->format ?? 'json';

        $data = $this->reportService->getTransactionReport(
            companyId: $companyId,
            startDate: $request->start_date,
            endDate: $request->end_date,
            type: $request->type,
            accountId: $request->account_id,
            categoryId: $request->category_id,
        );

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'transaction_report',
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'filters' => $request->only(['type', 'account_id', 'category_id']),
                'format' => $format,
                'record_count' => count($data['transactions'] ?? []),
            ])
        );

        if (in_array($format, ['excel', 'pdf', 'csv'])) {
            return $this->exportService->exportTransactions($data, $format);
        }

        return $this->successResponse($data);
    }

    /**
     * GET /api/reports/accounts
     * Reporte de cuentas con movimientos
     */
    public function accountReport(AccountReportRequest $request)  // ✅ Sin type hint
    {
        $companyId = $this->getCurrentCompanyId();
        $format = $request->format ?? 'json';

        $data = $this->reportService->getAccountSummary(
            companyId: $companyId,
            startDate: $request->start_date,
            endDate: $request->end_date,
        );

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'account_report',
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'format' => $format,
            ])
        );

        if (in_array($format, ['excel', 'pdf'])) {
            return $this->exportService->exportAccounts($data, $format);
        }

        return $this->successResponse($data);
    }

    /**
     * GET /api/reports/daily-summary
     * Resumen diario de movimientos
     */
    public function dailySummary(Request $request)  // ✅ Sin type hint
    {
        $companyId = $this->getCurrentCompanyId();
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $result = $this->reportService->getDailyEvolution(
            companyId: $companyId,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'daily_summary',
                'start_date' => $startDate,
                'end_date' => $endDate,
            ])
        );

        return $this->successResponse($result);
    }

    /**
     * GET /api/reports/monthly-comparison
     * Comparación mensual
     */
    public function monthlyComparison(MonthlyComparisonRequest $request)
    {
        $companyId = $this->getCurrentCompanyId();
        $format = $request->format ?? 'json';

        // ✅ Asegurar valores por defecto
        $year = $request->year ?? (int) date('Y');
        $months = $request->months ?? 12;

        $data = $this->reportService->getMonthlyComparison(
            companyId: $companyId,
            year: $year,
            months: $months,
        );

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'monthly_comparison',
                'year' => $year,
                'months' => $months,
                'format' => $format,
            ])
        );

        if (in_array($format, ['excel', 'pdf'])) {
            return $this->exportService->exportMonthlyComparison($data, $format);
        }

        return $this->successResponse($data);
    }

    /**
     * GET /api/reports/yearly-summary
     * Resumen anual
     */
    public function yearlySummary(YearlySummaryRequest $request)
    {
        $companyId = $this->getCurrentCompanyId();
        $format = $request->format ?? 'json';

        // ✅ Asegurar valores por defecto
        $currentYear = (int) date('Y');
        $startYear = $request->start_year ?? ($currentYear - 5);
        $endYear = $request->end_year ?? $currentYear;

        $data = $this->reportService->getYearlySummary(
            companyId: $companyId,
            startYear: $startYear,
            endYear: $endYear,
        );

        $this->logActivity(
            action: 'view_report',
            entityType: 'Report',
            entityId: 0,
            note: json_encode([
                'report_type' => 'yearly_summary',
                'start_year' => $startYear,
                'end_year' => $endYear,
                'format' => $format,
            ])
        );

        if (in_array($format, ['excel', 'pdf'])) {
            return $this->exportService->exportYearlySummary($data, $format);
        }

        return $this->successResponse($data);
    }
}
