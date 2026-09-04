<?php

namespace App\Filament\Resources\ImportSessions\Pages;

use App\Filament\Resources\ImportSessions\ImportSessionResource;
use App\Filament\Tables\Columns\AccountSelectColumn;
use App\Filament\Tables\Columns\CategorySelectColumn;
use App\Models\ImportedTransaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\ImportSession;
use App\Services\ClassificationService;
use App\Services\TransactionImportService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class ClassifyTransactions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ImportSessionResource::class;
    protected string $view = 'filament.resources.import-sessions.pages.classify-transactions';
    protected static ?string $title = 'Clasificar Transacciones Importadas';

    public ImportSession $session;

    public function mount($record): void
    {
        if (!isset($this->session)) {
            $this->session = ImportSession::findOrFail($record);
        }

        if ($this->session->processed_rows == 0) {
            Notification::make()
                ->warning()
                ->title('No hay transacciones para clasificar')
                ->body('Esta sesión no tiene transacciones importadas.')
                ->send();

            redirect()->route('filament.admin.resources.import-sessions.index');
            return;
        }
    }

    protected function getTableQuery(): Builder
    {
        if (!isset($this->session)) {
            return ImportedTransaction::query()->whereRaw('1 = 0');
        }

        return ImportedTransaction::query()
            ->where('import_session_id', $this->session->id)
            ->where('is_processed', false);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('transaction_date')
                ->label('Fecha')
                ->date()
                ->sortable(),

            TextColumn::make('reference')
                ->label('Referencia')
                ->searchable()
                ->limit(15),

            TextColumn::make('description')
                ->label('Descripción')
                ->searchable()
                ->limit(40)
                ->tooltip(fn($record) => $record->description),

            TextColumn::make('amount')
                ->label('Monto')
                ->money('VES')
                ->sortable(),

            TextColumn::make('transaction_type')
                ->label('Tipo')
                ->badge()
                ->color(fn($state) => $state === 'income' ? 'success' : 'danger')
                ->formatStateUsing(fn($state) => $state === 'income' ? '💰 Ingreso' : '💸 Egreso'),

            // ✅ Selector de categoría sin sobrescribir updateState
            SelectColumn::make('mapped_category')
                ->label('Categoría')
                ->options(function ($record) {
                    if (!$record) return [];
                    $service = app(ClassificationService::class);
                    return $service->getCategoriesByType($record->transaction_type);
                })
                ->searchable()
                ->placeholder('Seleccionar categoría')
                ->afterStateUpdated(function ($record, $state) {
                    // ✅ Al cambiar categoría, limpiar la cuenta si no es válida
                    if ($state && $record->mapped_account_id) {
                        $service = app(ClassificationService::class);
                        if (!$service->validateAccountCategory($record->mapped_account_id, $state)) {
                            $record->mapped_account_id = null;
                            $record->save();
                        }
                    }
                }),

            // ✅ Selector de cuenta sin sobrescribir updateState
            SelectColumn::make('mapped_account_id')
                ->label('Cuenta contable')
                ->options(function ($record) {
                    if (!$record) return [];

                    $service = app(ClassificationService::class);
                    $categoryName = $record->mapped_category;

                    if ($categoryName) {
                        return $service->getAccountsByCategory($categoryName);
                    }

                    return $service->getAccountsByType($record->transaction_type);
                })
                ->searchable()
                ->placeholder('Seleccionar cuenta')
                ->afterStateUpdated(function ($record, $state) {
                    // ✅ Al seleccionar cuenta, actualizar categoría automáticamente
                    if ($state) {
                        $account = Account::with('category')->find($state);
                        if ($account && $account->category) {
                            if ($account->category->type === $record->transaction_type) {
                                $record->mapped_category = $account->category->name;
                                $record->save();
                            }
                        }
                    }
                }),

        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('transaction_type')
                ->label('Tipo')
                ->options([
                    'income' => 'Ingresos',
                    'expense' => 'Egresos',
                ]),

            Filter::make('unclassified')
                ->label('Solo sin clasificar')
                ->default(true)
                ->query(fn(Builder $query) => $query->whereNull('mapped_account_id')),

            Filter::make('with_suggestions')
                ->label('Con sugerencias')
                ->query(function (Builder $query) {
                    // Esta es una implementación básica
                    // Idealmente se haría con una subconsulta
                    return $query->whereNotNull('description');
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            // ✅ Acción individual para aplicar sugerencia
            Action::make('apply_suggestion')
                ->label('Aplicar sugerencia')
                ->icon(Heroicon::LightBulb)
                ->color('info')
                ->action(function ($record) {
                    $service = app(ClassificationService::class);
                    $suggestion = $service->suggestCategory($record->description, $record->transaction_type);

                    if ($suggestion) {
                        $record->mapped_category = $suggestion;
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Sugerencia aplicada')
                            ->body("Se asignó la categoría: {$suggestion}")
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('Sin sugerencia')
                            ->body('No se pudo sugerir una categoría para esta transacción.')
                            ->send();
                    }
                })
                ->visible(fn($record) => !$record->mapped_category && $record->description),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('classify_selected')
                ->label('Clasificar seleccionadas')
                ->icon(Heroicon::CheckCircle)
                ->form([
                    Select::make('category_name')
                        ->label('Categoría')
                        ->options(function ($livewire) {
                            $type = $livewire->getSelectedRecords()->first()?->transaction_type;
                            if ($type) {
                                return app(ClassificationService::class)->getCategoriesByType($type);
                            }
                            return [];
                        })
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set, $get, $livewire) {
                            // ✅ Al seleccionar categoría, cargar cuentas
                            if ($state) {
                                $service = app(ClassificationService::class);
                                $accounts = $service->getAccountsByCategory($state);
                                $set('account_id', null);
                            }
                        }),

                    Select::make('account_id')
                        ->label('Cuenta contable')
                        ->options(function ($get, $livewire) {
                            $categoryName = $get('category_name');
                            if ($categoryName) {
                                return app(ClassificationService::class)->getAccountsByCategory($categoryName);
                            }

                            // Si no hay categoría, mostrar cuentas por tipo
                            $type = $livewire->getSelectedRecords()->first()?->transaction_type;
                            if ($type) {
                                return app(ClassificationService::class)->getAccountsByType($type);
                            }
                            return [];
                        })
                        ->required()
                        ->reactive(),
                ])
                ->action(function (array $data, $records) {
                    $count = 0;
                    $service = app(ClassificationService::class);

                    foreach ($records as $record) {
                        // ✅ Validar que la cuenta pertenece a la categoría
                        if ($data['account_id'] && $data['category_name']) {
                            if (!$service->validateAccountCategory($data['account_id'], $data['category_name'])) {
                                continue;
                            }
                        }

                        $record->mapped_category = $data['category_name'];
                        $record->mapped_account_id = $data['account_id'];
                        $record->save();
                        $count++;
                    }

                    Notification::make()
                        ->success()
                        ->title('Clasificación completada')
                        ->body("Se clasificaron {$count} transacciones.")
                        ->send();
                }),

            BulkAction::make('process_selected')
                ->label('Procesar seleccionadas')
                ->color('success')
                ->icon(Heroicon::ArrowRightCircle)
                ->requiresConfirmation()
                ->action(function ($records) {
                    $service = app(TransactionImportService::class);
                    $result = $service->processImportedTransactions($this->session->id);

                    Notification::make()
                        ->success()
                        ->title('Procesamiento completado')
                        ->body("Procesadas: {$result['processed']}, Errores: {$result['errors']}")
                        ->send();

                    return redirect()->route('filament.admin.resources.import-sessions.index');
                }),
        ];
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            Action::make('process_all')
                ->label('Procesar todas')
                ->color('success')
                ->icon(Heroicon::CheckCircle)
                ->requiresConfirmation()
                ->modalDescription('¿Estás seguro de procesar todas las transacciones clasificadas?')
                ->action(function () {
                    $service = app(TransactionImportService::class);
                    $result = $service->processImportedTransactions($this->session->id);

                    Notification::make()
                        ->success()
                        ->title('Procesamiento completado')
                        ->body("Procesadas: {$result['processed']}, Errores: {$result['errors']}")
                        ->send();

                    return redirect()->route('filament.admin.resources.import-sessions.index');
                }),
        ];
    }

    protected function refreshTable(): void
    {
        $this->dispatch('refresh-table');
    }
}
