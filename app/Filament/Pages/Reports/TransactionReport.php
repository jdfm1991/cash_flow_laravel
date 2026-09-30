<?php

namespace App\Filament\Pages\Reports;

use App\Exports\TransactionsExport;
use App\Filament\Traits\HasPermissions;
use App\Services\ReportService;
use App\Models\Category;
use App\Models\Account;
use App\Models\BankAccount;
use App\Services\AuditService;
use App\Services\PdfExportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class TransactionReport extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPermissions;

    // ✅ Configuración de la página
    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Transacciones';
    protected static ?string $title = 'Reporte de Transacciones';
    protected string $view = 'filament.pages.reports.transaction-report';

    // ✅ Filtros del reporte
    public ?array $filters = [];

    public static function getPermissionBase(): string
    {
        return 'transaction_report';
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
            'type' => null,
            'account_id' => null,
            'category_id' => null,
            'bank_account_id' => null,
        ];
    }

    /**
     * ✅ FILAMENT 4: Formulario con Schema
     */
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

                // ✅ 1. Tipo (afecta a categoría y cuenta contable)
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'income' => 'Ingresos',
                        'expense' => 'Egresos',
                        'transfer' => 'Transferencias',
                    ])
                    ->placeholder('Todos')
                    ->reactive()  // ✅ Para que dispare cambios
                    ->afterStateUpdated(function ($state, callable $set) {
                        // ✅ Limpiar dependencias al cambiar tipo
                        $set('category_id', null);
                        $set('account_id', null);
                    }),

                // ✅ 2. Categoría (depende del tipo)
                Select::make('category_id')
                    ->label('Categoría')
                    ->options(function (callable $get) {
                        $type = $get('type');
                        $query = Category::where('is_active', true);

                        if ($type) {
                            $query->where('type', $type);
                        }

                        return $query->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas')
                    ->reactive()  // ✅ Para que dispare cambios
                    ->afterStateUpdated(function ($state, callable $set) {
                        // ✅ Limpiar cuenta contable al cambiar categoría
                        $set('account_id', null);
                    }),

                // ✅ 3. Cuenta Contable (depende de la categoría)
                Select::make('account_id')
                    ->label('Cuenta contable')
                    ->options(function (callable $get) {
                        $categoryId = $get('category_id');
                        $query = Account::where('is_active', true);

                        if ($categoryId) {
                            $query->where('category_id', $categoryId);
                        }

                        return $query->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas'),

                // ✅ 4. Cuenta Bancaria (independiente)
                Select::make('bank_account_id')
                    ->label('Cuenta bancaria')
                    ->options(fn() => BankAccount::where('is_active', true)
                        ->with('bank')
                        ->get()
                        ->mapWithKeys(fn($item) => [
                            $item->id => $item->alias . ' (' . ($item->bank?->name ?? 'N/A') . ')'
                        ]))
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas'),
            ])
            ->statePath('filters')
            ->columns(3);
    }

    /**
     * Obtener los datos del reporte
     */
    public function getReportData(): array
    {
        $reportService = app(ReportService::class);
        $companyId = Auth::user()->current_company_id;

        // ✅ Convertir valores vacíos a null y asegurar tipo correcto
        $accountId = $this->filters['account_id'] ?? null;
        $categoryId = $this->filters['category_id'] ?? null;
        $bankAccountId = $this->filters['bank_account_id'] ?? null;

        return $reportService->getTransactionReport(
            companyId: $companyId,
            startDate: $this->filters['start_date'] ?? now()->startOfMonth()->toDateString(),
            endDate: $this->filters['end_date'] ?? now()->toDateString(),
            type: $this->filters['type'] ?? null,
            accountId: !empty($accountId) ? (int) $accountId : null,  // ✅ Convertir a int
            categoryId: !empty($categoryId) ? (int) $categoryId : null,  // ✅ Convertir a int
            bankAccountId: !empty($bankAccountId) ? (int) $bankAccountId : null,  // ✅ Convertir a int
        );
    }

    /**
     * Obtener datos para la vista
     */
    public function getViewData(): array
    {
        $data = $this->getReportData();

        // ✅ Calcular conteos por tipo
        $transactions = collect($data['transactions'] ?? []);
        $incomeCount = $transactions->where('type', 'income')->count();
        $expenseCount = $transactions->where('type', 'expense')->count();

        return [
            'summary' => array_merge($data['summary'] ?? [], [
                'income_count' => $incomeCount,
                'expense_count' => $expenseCount,
            ]),
            'transactions' => $data['transactions'] ?? [],
            'period' => $data['period'] ?? [],
            'filters' => [
                'type' => $this->filters['type'] ?? null,
                'account' => Account::find($this->filters['account_id'] ?? 0)?->name ?? null,
                'category' => Category::find($this->filters['category_id'] ?? 0)?->name ?? null,
                'bank_account' => BankAccount::find($this->filters['bank_account_id'] ?? 0)?->alias ?? null,
            ],
        ];
    }

    /**
     * Configuración de la tabla
     */
    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $data = $this->getReportData();
        $transactions = collect($data['transactions'] ?? []);
        $ids = $transactions->pluck('id')->toArray();

        if (empty($ids)) {
            return \App\Models\Transaction::query()->whereRaw('1 = 0');
        }

        return \App\Models\Transaction::query()
            ->with(['account', 'category', 'bankAccount', 'currency'])
            ->whereIn('id', $ids);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('date')
                ->label('Fecha')
                ->date('d/m/Y')
                ->sortable()
                ->toggleable(),

            TextColumn::make('type_label')
                ->label('Tipo')
                ->badge()
                ->color(fn($state) => match ($state) {
                    'Ingreso' => 'success',
                    'Egreso' => 'danger',
                    'Transferencia' => 'warning',
                    default => 'gray',
                })
                ->toggleable(),

            TextColumn::make('description')
                ->label('Descripción')
                ->searchable()
                ->limit(30)
                ->tooltip(fn($record) => $record->description)
                ->toggleable(),

            TextColumn::make('category.name')
                ->label('Categoría')
                ->sortable()
                ->toggleable(),

            TextColumn::make('account.name')
                ->label('Cuenta')
                ->sortable()
                ->toggleable(),

            TextColumn::make('bankAccount.alias')
                ->label('Banco')
                ->sortable()
                ->toggleable(),

            // ✅ Monto Original (en la moneda de la transacción)
            TextColumn::make('amount')
                ->label('Monto VES')
                ->formatStateUsing(
                    fn($record) => ($record->currency?->code ?? '') . ' ' . number_format($record->amount, 2)
                )
                ->sortable()
                ->toggleable(),

            // ✅ Monto Convertido (en USD)
            TextColumn::make('amount_converted')
                ->label('Monto $USD')
                ->money('USD')
                ->sortable()
                ->toggleable(),

            TextColumn::make('currency.code')
                ->label('Moneda')
                ->badge()
                ->color('gray')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('reference')
                ->label('Referencia')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
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

        // ✅ Registrar auditoría
        app(AuditService::class)->logExport('Transactions Report - Excel');

        return (new TransactionsExport($data))
            ->download('transacciones_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }

    /**
     * Exportar a PDF
     */
    public function exportPdf()
    {
        try {
            $data = $this->getReportData();

            // ✅ Registrar auditoría
            app(AuditService::class)->logExport('Transactions Report - PDF');

            // ✅ Validar que hay datos
            if (empty($data['transactions'])) {
                Notification::make()
                    ->warning()
                    ->title('Sin datos')
                    ->body('No hay transacciones para exportar en el período seleccionado.')
                    ->send();
                return redirect()->back();
            }

            // Obtener la empresa
            $company = Auth::user()->currentCompany;

            // Calcular conteos
            $transactions = collect($data['transactions'] ?? []);
            $incomeCount = $transactions->where('type', 'income')->count();
            $expenseCount = $transactions->where('type', 'expense')->count();

            // Preparar datos
            $viewData = [
                'title' => 'Reporte de Transacciones',
                'companyName' => $company?->name ?? 'Empresa',
                'period' => $data['period'] ?? [],
                'summary' => array_merge($data['summary'] ?? [], [
                    'income_count' => $incomeCount,
                    'expense_count' => $expenseCount,
                ]),
                'transactions' => $data['transactions'] ?? [],
                'filters' => [
                    'type' => $this->filters['type'] ?? null,
                    'account' => Account::find($this->filters['account_id'] ?? 0)?->name ?? null,
                    'category' => Category::find($this->filters['category_id'] ?? 0)?->name ?? null,
                    'bank_account' => BankAccount::find($this->filters['bank_account_id'] ?? 0)?->alias ?? null,
                ],
                'generated_at' => now()->format('d/m/Y H:i:s'),
                'base_currency' => 'VES',
            ];

            // ✅ Verificar que la vista existe
            if (!view()->exists('reports.pdf.transaction-report')) {
                throw new \Exception('La vista PDF no existe: reports.pdf.transaction-report');
            }

            // Generar PDF
            $pdfService = app(PdfExportService::class);
            return $pdfService->generatePdfWithoutCharts(
                'reports.pdf.transaction-report',
                $viewData,
                'transacciones_' . now()->format('Y-m-d_H-i-s')
            );
        } catch (\Exception $e) {
            Log::error('Error exportando PDF de transacciones', [
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
