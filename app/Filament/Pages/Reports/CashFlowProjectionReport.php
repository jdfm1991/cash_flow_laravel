<?php

namespace App\Filament\Pages\Reports;

use App\Services\ChartImageService;
use App\Services\PdfExportService;
use App\Services\ProjectionService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class CashFlowProjectionReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Proyección de Flujo';
    protected static ?string $title = 'Proyección de Flujo de Caja';
    protected string $view = 'filament.pages.reports.cash-flow-projection-report';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'months' => 12,
            'scenario' => 'realistic',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('months')
                    ->label('Horizonte de proyección')
                    ->options([
                        3 => '3 meses',
                        6 => '6 meses',
                        12 => '12 meses',
                        18 => '18 meses',
                        24 => '24 meses',
                    ])
                    ->default(12)
                    ->required(),
                Select::make('scenario')
                    ->label('Escenario')
                    ->options([
                        'realistic' => '📊 Realista',
                        'optimistic' => '📈 Optimista',
                    ])
                    ->default('realistic')
                    ->required(),
            ])
            ->statePath('filters')
            ->columns(2);
    }

    public function getProjectionData(): array
    {
        $service = app(ProjectionService::class);
        $companyId = Auth::user()->current_company_id;

        $months = (int) ($this->filters['months'] ?? 12);
        $scenario = $this->filters['scenario'] ?? 'realistic';

        return $service->calculateProjection($companyId, $months, $scenario);
    }

    public function getViewData(): array
    {
        $data = $this->getProjectionData();

        $labels = array_column($data['projection'], 'month_name');
        $incomeData = array_column($data['projection'], 'income');
        $expenseData = array_column($data['projection'], 'expense');
        $balanceData = array_column($data['projection'], 'balance');

        return [
            'projection' => $data['projection'],
            'summary' => $data['summary'],
            'metrics' => $data['metrics'],
            'alerts' => $data['alerts'],
            'scenario_label' => $data['scenario_label'],
            'scenario_color' => $data['scenario_color'],
            'starting_balance' => $data['starting_balance'],
            'labels' => $labels,
            'incomeData' => $incomeData,
            'expenseData' => $expenseData,
            'balanceData' => $balanceData,
        ];
    }

    public function refresh(): void
    {
        $this->dispatch('refresh-data');
    }

    /**
     * ✅ Exportar a PDF
     */
    public function exportPdf()
    {
        try {
            $data = $this->getProjectionData();
            $viewData = $this->getViewData();

            if (empty($data['projection'])) {
                Notification::make()
                    ->warning()
                    ->title('Sin datos')
                    ->body('No hay datos suficientes para generar la proyección.')
                    ->send();
                return redirect()->back();
            }

            $company = Auth::user()->currentCompany;

            // ✅ Generar gráfico para el PDF
            $chartImagePath = null;
            if (!empty($viewData['labels'])) {
                $chartService = app(ChartImageService::class);
                $chartImagePath = $chartService->generateLineChart(
                    labels: $viewData['labels'],
                    incomeData: $viewData['incomeData'],
                    expenseData: $viewData['expenseData'],
                    netData: $viewData['balanceData'],
                    title: 'Proyección de Flujo de Caja - ' . $viewData['scenario_label'],
                    width: 700,
                    height: 350
                );
            }

            // Preparar datos para el PDF
            $pdfData = [
                'title' => 'Proyección de Flujo de Caja',
                'companyName' => $company?->name ?? 'Empresa',
                'scenario_label' => $viewData['scenario_label'],
                'scenario_color' => $viewData['scenario_color'],
                'projection' => $viewData['projection'],
                'summary' => $viewData['summary'],
                'metrics' => $viewData['metrics'],
                'alerts' => $viewData['alerts'],
                'generated_at' => now()->format('d/m/Y H:i:s'),
                'chart_image_path' => $chartImagePath,
            ];

            $pdfService = app(PdfExportService::class);
            return $pdfService->generatePdf(
                'reports.pdf.cash-flow-projection-report',
                $pdfData,
                'proyeccion_flujo_' . now()->format('Y-m-d_H-i-s')
            );
        } catch (\Exception $e) {
            Log::error('Error exportando PDF de proyección', [
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
