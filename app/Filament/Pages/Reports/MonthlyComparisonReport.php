<?php

namespace App\Filament\Pages\Reports;

use App\Exports\MonthlyComparisonExport;
use App\Filament\Traits\HasPermissions;
use App\Services\PdfExportService;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class MonthlyComparisonReport extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPermissions;

    // ✅ Configuración de la página
    protected static string|BackedEnum|null  $navigationIcon = Heroicon::ChartBar;
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Comparativo Mensual';
    protected static ?string $title = 'Reporte Comparativo Mensual';
    protected string $view = 'filament.pages.reports.monthly-comparison-report';

    // ✅ Filtros del reporte
    public ?array $filters = [];

    public static function getPermissionBase(): string
    {
        return 'monthly_comparison_report';
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
            'year' => (int) date('Y'),
            'months' => 12,
        ];
    }

    /**
     * ✅ FILAMENT 4: Formulario con Schema
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('year')
                    ->label('Año')
                    ->options(fn() => $this->getYearOptions())
                    ->default((int) date('Y'))
                    ->required(),
                Select::make('months')
                    ->label('Meses a mostrar')
                    ->options([
                        6 => '6 meses',
                        12 => '12 meses',
                        18 => '18 meses',
                        24 => '24 meses',
                    ])
                    ->default(12)
                    ->required(),
            ])
            ->statePath('filters')
            ->columns(2);
    }

    /**
     * Obtener opciones de años
     */
    protected function getYearOptions(): array
    {
        $currentYear = (int) date('Y');
        $options = [];
        for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
            $options[$year] = $year;
        }
        return $options;
    }

    /**
     * Obtener los datos del reporte
     */
    public function getReportData(): array
    {
        $reportService = app(ReportService::class);
        $companyId = Auth::user()->current_company_id;

        $year = $this->filters['year'] ?? (int) date('Y');
        $months = $this->filters['months'] ?? 12;

        return $reportService->getMonthlyComparison(
            companyId: $companyId,
            year: $year,
            months: $months,
        );
    }

    /**
     * Obtener datos para la vista (con gráficos)
     */
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

        foreach ($data as $month) {
            // ✅ Usar nombre corto para el gráfico
            $labels[] = $month['month_name_short'] ?? $month['month_name'];

            $incomeData[] = $month['income'];
            $expenseData[] = $month['expense'];
            $netData[] = $month['net'];

            $incomeOriginalData[] = $month['income_original'];
            $expenseOriginalData[] = $month['expense_original'];
            $netOriginalData[] = $month['net_original'];

            $totals['income'] += $month['income'];
            $totals['expense'] += $month['expense'];
            $totals['net'] += $month['net'];
            $totals['income_original'] += $month['income_original'];
            $totals['expense_original'] += $month['expense_original'];
            $totals['net_original'] += $month['net_original'];
        }

        $baseCurrency = \App\Models\Currency::where('is_base', true)->first();
        $baseCurrencyCode = $baseCurrency?->code ?? 'VES';

        return [
            'months' => $data,
            'labels' => $labels,
            'incomeData' => $incomeData,
            'expenseData' => $expenseData,
            'netData' => $netData,
            'incomeOriginalData' => $incomeOriginalData,
            'expenseOriginalData' => $expenseOriginalData,
            'netOriginalData' => $netOriginalData,
            'totals' => $totals,
            'year' => $this->filters['year'] ?? date('Y'),
            'base_currency' => $baseCurrencyCode,
        ];
    }

    /**
     * Configuración de la tabla
     */
    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // La tabla muestra datos agregados, no transacciones individuales
        return \App\Models\Transaction::query()->whereRaw('1 = 0');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('month_name')
                ->label('Mes')
                ->searchable(),
            TextColumn::make('income')
                ->label('Ingresos')
                ->money('VES')
                ->sortable(),
            TextColumn::make('expense')
                ->label('Egresos')
                ->money('VES')
                ->sortable(),
            TextColumn::make('net')
                ->label('Neto')
                ->money('VES')
                ->color(fn($record) => ($record['net'] ?? 0) >= 0 ? 'success' : 'danger')
                ->sortable(),
        ];
    }

    /**
     * Datos para la tabla (sin query real)
     */
    public function getTableData(): array
    {
        return $this->getReportData();
    }

    /**
     * Acción para actualizar el reporte
     */
    public function refresh(): void
    {
        $this->dispatch('refresh-table');
    }

    /**
     * Exportar a Excel
     */
    public function exportExcel()
    {
        $data = $this->getReportData();

        return (new MonthlyComparisonExport($data))
            ->download('comparativo_mensual_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }

    /**
     * Exportar a PDF
     */
    /**
     * Exportar a PDF
     */
    public function exportPdf()
    {
        try {
            $data = $this->getReportData();
            $viewData = $this->getViewData();

            // Validar que hay datos
            if (empty($data)) {
                Notification::make()
                    ->warning()
                    ->title('Sin datos')
                    ->body('No hay datos para exportar en el período seleccionado.')
                    ->send();
                return redirect()->back();
            }

            $company = Auth::user()->currentCompany;

            // ✅ Preparar datos para el gráfico
            $chartData = [
                'labels' => $viewData['labels'] ?? [],
                'incomeData' => $viewData['incomeData'] ?? [],
                'expenseData' => $viewData['expenseData'] ?? [],
                'netData' => $viewData['netData'] ?? [],
            ];

            // Preparar datos para la vista del PDF
            $pdfData = [
                'title' => 'Reporte Comparativo Mensual',
                'companyName' => $company?->name ?? 'Empresa',
                'year' => $this->filters['year'] ?? date('Y'),
                'months' => $viewData['months'] ?? [],
                'totals' => $viewData['totals'] ?? [],
                'chart_labels' => $chartData['labels'] ?? [],
                'chart_income' => $chartData['incomeData'] ?? [],
                'chart_expense' => $chartData['expenseData'] ?? [],
                'chart_net' => $chartData['netData'] ?? [],
                'generated_at' => now()->format('d/m/Y H:i:s'),
                'base_currency' => $viewData['base_currency'] ?? 'VES',
            ];

            // ✅ Generar PDF usando el servicio con gráficos
            $pdfService = app(PdfExportService::class);
            return $pdfService->generatePdf(
                'reports.pdf.monthly-comparison-report',
                $pdfData,
                'comparativo_mensual_' . now()->format('Y-m-d_H-i-s')
            );
        } catch (\Exception $e) {
            Log::error('Error exportando PDF comparativo mensual', [
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
