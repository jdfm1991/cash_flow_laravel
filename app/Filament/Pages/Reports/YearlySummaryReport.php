<?php

namespace App\Filament\Pages\Reports;

use App\Exports\YearlySummaryExport;
use App\Filament\Traits\HasPermissions;
use App\Services\ReportService;
use App\Models\Currency;
use App\Services\AuditService;
use App\Services\PdfExportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use UnitEnum;

class YearlySummaryReport extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPermissions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Calendar;
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Resumen Anual';
    protected static ?string $title = 'Resumen Anual';
    protected string $view = 'filament.pages.reports.yearly-summary-report';

    public ?array $filters = [];

    public static function getPermissionBase(): string
    {
        return 'yearly_summary_report';
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
        $currentYear = (int) date('Y');
        $this->filters = [
            'start_year' => $currentYear - 5,
            'end_year' => $currentYear,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('start_year')
                    ->label('Año inicio')
                    ->options(fn() => $this->getYearOptions())
                    ->default((int) date('Y') - 5)
                    ->required(),
                Select::make('end_year')
                    ->label('Año fin')
                    ->options(fn() => $this->getYearOptions())
                    ->default((int) date('Y'))
                    ->required(),
            ])
            ->statePath('filters')
            ->columns(2);
    }

    protected function getYearOptions(): array
    {
        $currentYear = (int) date('Y');
        $options = [];
        for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
            $options[$year] = $year;
        }
        return $options;
    }

    public function getReportData(): array
    {
        $reportService = app(ReportService::class);
        $companyId = Auth::user()->current_company_id;

        $startYear = $this->filters['start_year'] ?? ((int) date('Y') - 5);
        $endYear = $this->filters['end_year'] ?? (int) date('Y');

        return $reportService->getYearlySummary(
            companyId: $companyId,
            startYear: $startYear,
            endYear: $endYear,
        );
    }

    public function getViewData(): array
    {
        $data = $this->getReportData();

        $labels = [];
        $incomeData = [];
        $expenseData = [];
        $netData = [];
        $incomeOriginalData = [];
        $expenseOriginalData = [];
        $netOriginalData = [];

        $totals = [
            'income' => 0,
            'expense' => 0,
            'net' => 0,
            'income_original' => 0,
            'expense_original' => 0,
            'net_original' => 0,
        ];

        foreach ($data as $year) {
            $labels[] = $year['year'];

            $incomeData[] = $year['income'];
            $expenseData[] = $year['expense'];
            $netData[] = $year['net'];

            $incomeOriginalData[] = $year['income_original'];
            $expenseOriginalData[] = $year['expense_original'];
            $netOriginalData[] = $year['net_original'];

            $totals['income'] += $year['income'];
            $totals['expense'] += $year['expense'];
            $totals['net'] += $year['net'];
            $totals['income_original'] += $year['income_original'];
            $totals['expense_original'] += $year['expense_original'];
            $totals['net_original'] += $year['net_original'];
        }

        $baseCurrency = Currency::where('is_base', true)->first();
        $baseCurrencyCode = $baseCurrency?->code ?? 'VES';

        return [
            'years' => $data,
            'labels' => $labels,
            'incomeData' => $incomeData,
            'expenseData' => $expenseData,
            'netData' => $netData,
            'incomeOriginalData' => $incomeOriginalData,
            'expenseOriginalData' => $expenseOriginalData,
            'netOriginalData' => $netOriginalData,
            'totals' => $totals,
            'start_year' => $this->filters['start_year'] ?? ((int) date('Y') - 5),
            'end_year' => $this->filters['end_year'] ?? (int) date('Y'),
            'base_currency' => $baseCurrencyCode,
        ];
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\Transaction::query()->whereRaw('1 = 0');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('year')
                ->label('Año')
                ->searchable()
                ->sortable(),
            TextColumn::make('income')
                ->label('Ingresos (VES)')
                ->money('VES')
                ->sortable(),
            TextColumn::make('expense')
                ->label('Egresos (VES)')
                ->money('VES')
                ->sortable(),
            TextColumn::make('net')
                ->label('Neto (VES)')
                ->money('VES')
                ->color(fn($record) => ($record['net'] ?? 0) >= 0 ? 'success' : 'danger')
                ->sortable(),
            TextColumn::make('income_count')
                ->label('N° Ingresos')
                ->sortable(),
            TextColumn::make('expense_count')
                ->label('N° Egresos')
                ->sortable(),
        ];
    }

    public function getTableData(): array
    {
        return $this->getReportData();
    }

    public function refresh(): void
    {
        $this->dispatch('refresh-table');
    }

    public function exportExcel()
    {
        $data = $this->getReportData();

        // ✅ Registrar auditoría
        app(AuditService::class)->logExport('Yearly Summary report - Excel');

        return (new YearlySummaryExport($data))
            ->download('resumen_anual_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }

    public function exportPdf()
    {
        try {
            $data = $this->getReportData();

            // ✅ Registrar auditoría
            app(AuditService::class)->logExport('Yearly Summary report - PDF');

            $viewData = $this->getViewData();

            if (empty($data)) {
                Notification::make()
                    ->warning()
                    ->title('Sin datos')
                    ->body('No hay datos para exportar en el período seleccionado.')
                    ->send();
                return redirect()->back();
            }

            $company = Auth::user()->currentCompany;

            $pdfData = [
                'title' => 'Resumen Anual',
                'companyName' => $company?->name ?? 'Empresa',
                'start_year' => $viewData['start_year'],
                'end_year' => $viewData['end_year'],
                'years' => $viewData['years'] ?? [],
                'totals' => $viewData['totals'] ?? [],
                'chart_labels' => $viewData['labels'] ?? [],
                'chart_income' => $viewData['incomeData'] ?? [],
                'chart_expense' => $viewData['expenseData'] ?? [],
                'chart_net' => $viewData['netData'] ?? [],
                'generated_at' => now()->format('d/m/Y H:i:s'),
                'base_currency' => $viewData['base_currency'] ?? 'VES',
            ];

            $pdfService = app(PdfExportService::class);
            return $pdfService->generatePdf(
                'reports.pdf.yearly-summary-report',
                $pdfData,
                'resumen_anual_' . now()->format('Y-m-d_H-i-s')
            );
        } catch (\Exception $e) {
            Log::error('Error exportando PDF resumen anual', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->danger()
                ->title('Error al generar PDF')
                ->body($e->getMessage())
                ->send();

            return redirect()->back();
        }
    }
}
