<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('alias')
                    ->searchable()
                    ->sortable()
                    ->label('Alias')
                    ->description(fn ($record) => $record->account_number),
                TextColumn::make('bank.name')
                    ->searchable()
                    ->sortable()
                    ->label('Banco')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('account_number')
                    ->searchable()
                    ->sortable()
                    ->label('Número de cuenta')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('account_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'corriente' => 'primary',
                        'ahorros' => 'success',
                        'nomina' => 'info',
                        'inversion' => 'warning',
                        'caja_chica' => 'secondary',
                        'efectivo' => 'success',
                        'tarjeta_credito' => 'danger',
                        'virtual' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'corriente' => 'Corriente',
                        'ahorros' => 'Ahorros',
                        'nomina' => 'Nómina',
                        'inversion' => 'Inversión',
                        'caja_chica' => 'Caja Chica',
                        'efectivo' => 'Efectivo',
                        'tarjeta_credito' => 'Tarjeta Crédito',
                        'virtual' => 'Virtual',
                        default => $state,
                    }),
                TextColumn::make('currency.code')
                    ->label('Moneda')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('opening_balance')
                    ->label('Saldo inicial')
                    ->money(fn ($record) => $record->currency?->code ?? 'USD')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Predeterminada')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-star')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Empresa'),
                SelectFilter::make('bank_id')
                    ->relationship('bank', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Banco'),
                SelectFilter::make('account_type')
                    ->options([
                        'corriente' => 'Corriente',
                        'ahorros' => 'Ahorros',
                        'nomina' => 'Nómina',
                        'inversion' => 'Inversión',
                        'caja_chica' => 'Caja Chica',
                        'efectivo' => 'Efectivo',
                        'tarjeta_credito' => 'Tarjeta Crédito',
                        'virtual' => 'Virtual',
                    ]),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Activas',
                        '0' => 'Inactivas',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                ->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Editar Cuenta Bancaria')
                    ->modalSubmitActionLabel('Actualizar Información')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cuenta actualizada')
                            ->body('La cuenta bancaria se ha actualizado correctamente.')
                    ),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Cuenta Bancaria')
                    ->modalDescription('¿Estás seguro de eliminar esta cuenta bancaria?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cuenta eliminada')
                            ->body('La cuenta bancaria se ha eliminado correctamente.')
                    )
                    ->before(function ($record) {
                        if ($record->transactions()->count() > 0) {
                            throw new \Exception('No se puede eliminar una cuenta que tiene transacciones asociadas.');
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
