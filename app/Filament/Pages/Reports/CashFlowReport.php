<?php

namespace App\Filament\Pages\Reports;

use App\Exports\CashFlowExport;
use App\Filament\Traits\HasPermissions;
use App\Services\ReportService;
use App\Models\Category;
use App\Models\Account;
use App\Services\PdfExportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class CashFlowReport extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPermissions;

    protected string $view = 'filament.pages.reports.cash-flow-report';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentChartBar;
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Flujo de Caja';
    protected static ?string $title = 'Reporte de Flujo de Caja';

    public ?array $filters = [];

    public static function getPermissionBase(): string
    {
        return 'cash_flow_report';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->toDateString(),
            'category_id' => null,
            'account_id' => null,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('start_date')
                    ->label('Fecha inicio')
                    ->default(now()->startOfMonth())
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Fecha fin')
                    ->default(now())
                    ->required(),
                Select::make('category_id')
                    ->label('Categoría')
                    ->options(fn() => Category::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas'),
                Select::make('account_id')
                    ->label('Cuenta contable')
                    ->options(fn() => Account::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas'),
            ])
            ->statePath('filters')
            ->columns(4);
    }

    /**
     * Obtener los datos del reporte
     */
    public function getReportData(): array
    {
        $reportService = app(ReportService::class);
        $companyId = Auth::user()->current_company_id;

        return $reportService->getCashFlowReport(
            companyId: $companyId,
            startDate: $this->filters['start_date'] ?? now()->startOfMonth()->toDateString(),
            endDate: $this->filters['end_date'] ?? now()->toDateString(),
            categoryId: $this->filters['category_id'] ?? null,
            accountId: $this->filters['account_id'] ?? null,
        );
    }

    /**
     * ✅ Obtener datos para la vista con ambos montos
     */
    public function getViewData(): array
    {
        $data = $this->getReportData();
        $monthlySummary = $this->getMonthlyCategorySummary();
        $monthlyChartData = $this->getMonthlyChartData();

        // ✅ Obtener la moneda base (VES)
        $baseCurrency = \App\Models\Currency::where('is_base', true)->first();
        $baseCurrencyCode = $baseCurrency?->code ?? 'VES';

        return [
            'totals' => $data['totals'] ?? [],
            'period' => $data['period'] ?? [],
            'monthly_summary' => $monthlySummary,
            'chart_labels' => $monthlyChartData['labels'],
            'chart_income' => $monthlyChartData['incomeData'],
            'chart_expense' => $monthlyChartData['expenseData'],
            'chart_net' => $monthlyChartData['netData'],
            'base_currency' => $baseCurrencyCode, // ✅ Pasar a la vista
        ];
    }



    /**
     * ✅ Obtener resumen por categoría
     */
    public function getCategorySummary(): array
    {
        $reportService = app(ReportService::class);
        $companyId = Auth::user()->current_company_id;

        return $reportService->getCategorySummary(
            companyId: $companyId,
            startDate: $this->filters['start_date'] ?? now()->startOfMonth()->toDateString(),
            endDate: $this->filters['end_date'] ?? now()->toDateString(),
        );
    }

    /**
     * ✅ Obtener resumen por mes y categoría
     */
    public function getMonthlyCategorySummary(): array
    {
        $reportService = app(ReportService::class);
        $companyId = Auth::user()->current_company_id;

        return $reportService->getMonthlyCategorySummary(
            companyId: $companyId,
            startDate: $this->filters['start_date'] ?? now()->startOfMonth()->toDateString(),
            endDate: $this->filters['end_date'] ?? now()->toDateString(),
        );
    }

    /**
     * ✅ Obtener datos para gráficos de evolución mensual
     */
    public function getMonthlyChartData(): array
    {
        $monthlySummary = $this->getMonthlyCategorySummary();

        $labels = [];
        $incomeData = [];
        $expenseData = [];
        $netData = [];

        foreach ($monthlySummary as $month) {
            // ✅ Asegurar que el mes esté en español
            $labels[] = $month['month_name']; // Ya viene en español del ReportService
            $incomeData[] = $month['totals']['income'];
            $expenseData[] = $month['totals']['expense'];
            $netData[] = $month['totals']['net'];
        }

        return [
            'labels' => $labels,
            'incomeData' => $incomeData,
            'expenseData' => $expenseData,
            'netData' => $netData,
        ];
    }


    /**
     * ✅ Configuración de la tabla (ahora muestra el resumen)
     */
    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Ya no usamos la tabla para transacciones individuales
        return \App\Models\Transaction::query()->whereRaw('1 = 0');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('category')
                ->label('Categoría'),
            TextColumn::make('account')
                ->label('Cuenta'),
            TextColumn::make('total')
                ->label('Total')
                ->money('VES'),
            TextColumn::make('transaction_count')
                ->label('Transacciones'),
        ];
    }

    public function refresh(): void
    {
        $this->dispatch('refresh-table');
    }

    public function exportExcel()
    {
        $data = $this->getReportData();
        $summary = $this->getCategorySummary();

        $exportData = array_merge($data, [
            'category_summary' => $summary,
        ]);

        return (new CashFlowExport($exportData))
            ->download('flujo_caja_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }

    /**
     * Exportar a PDF
     */

    public function exportPdf()
    {
        // Obtener datos del reporte
        $data = $this->getReportData();
        $monthlySummary = $this->getMonthlyCategorySummary();
        $chartData = $this->getMonthlyChartData();

        // Debug: verificar que los datos del gráfico existen
        Log::info('Chart Data:', $chartData);

        // Obtener la empresa del usuario autenticado
        $company = Auth::user()->currentCompany;

        // Preparar datos para la vista del PDF
        $viewData = [
            'title' => 'Reporte de Flujo de Caja - Resumen por Mes',
            'companyName' => $company?->name ?? 'Empresa',
            'companyLogo' => $company?->logo_path ?? null, // Opcional: logo de la empresa
            'totals' => $data['totals'] ?? [],
            'period' => $data['period'] ?? [],
            'daily_evolution' => $data['daily_evolution'] ?? [],
            'monthly_summary' => $monthlySummary,
            'bank_account_summary' => $data['bank_account_summary'] ?? [],
            // ✅ DATOS DEL GRÁFICO - AHORA PASADOS CORRECTAMENTE
            'chart_labels' => $chartData['labels'] ?? [],
            'chart_income' => $chartData['incomeData'] ?? [],
            'chart_expense' => $chartData['expenseData'] ?? [],
            'chart_net' => $chartData['netData'] ?? [],
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'base_currency' => 'VES',
        ];

        // Generar PDF usando PdfExportService
        $pdfService = app(PdfExportService::class);

        return $pdfService->generatePdf(
            'reports.pdf.cash-flow-summary',
            $viewData,
            'flujo_caja_' . now()->format('Y-m-d_H-i-s')
        );
    }
}
