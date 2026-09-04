<?php

namespace App\Filament\Resources\Transactions\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'income' => 'success',
                        'expense' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'income' => 'Ingreso',
                        'expense' => 'Egreso',
                        'transfer' => 'Transferencia',
                        default => $state,
                    }),

                TextColumn::make('account.name')
                    ->label('Cuenta contable')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('bankAccount.alias')
                    ->label('Cuenta bancaria')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money(fn($record) => $record->currency?->code ?? 'USD')
                    ->sortable(),

                TextColumn::make('currency.code')
                    ->label('Moneda')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->description)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_reconciled')
                    ->label('Conciliado')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'income' => 'Ingresos',
                        'expense' => 'Egresos',
                        'transfer' => 'Transferencias',
                    ])
                    ->label('Tipo'),

                SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Empresa'),

                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Categoría'),

                SelectFilter::make('bank_account_id')
                    ->relationship('bankAccount', 'alias')
                    ->searchable()
                    ->preload()
                    ->label('Cuenta bancaria'),

                Filter::make('is_reconciled')
                    ->label('Conciliado')
                    ->query(fn($query) => $query->where('is_reconciled', true)),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('start_date'),
                        DatePicker::make('end_date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['start_date'] ?? null, fn($q, $date) =>
                            $q->whereDate('date', '>=', $date))
                            ->when($data['end_date'] ?? null, fn($q, $date) =>
                            $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Editar Transacción')
                    ->modalSubmitActionLabel('Actualizar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Transacción actualizada')
                            ->body('La transacción se ha actualizado correctamente.')
                    ),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Transacción')
                    ->modalDescription('¿Estás seguro de eliminar esta transacción?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Transacción eliminada')
                            ->body('La transacción se ha eliminado correctamente.')
                    )
                    // ✅ NOTIFICACIÓN DE ERROR
                    ->failureNotification(
                        Notification::make()
                            ->danger()
                            ->title('No se puede eliminar')
                            ->body('No se puede eliminar una transacción que ya está conciliada.')
                    )
                    ->before(function ($record, $action) {
                        if ($record->is_reconciled) {
                            $action->halt();

                            // Mostrar notificación de error
                            Notification::make()
                                ->danger()
                                ->title('No se puede eliminar')
                                ->body('No se puede eliminar una transacción que ya está conciliada.')
                                ->send();

                            return;
                        }
                    }),
            ])
            ->toolbarActions([
                /*  BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]), */]);
    }
}
