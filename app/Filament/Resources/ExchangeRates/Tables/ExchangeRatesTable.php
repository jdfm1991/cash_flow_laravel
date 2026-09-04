<?php

namespace App\Filament\Resources\ExchangeRates\Tables;

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

class ExchangeRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fromCurrency.code')
                    ->label('Origen')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('toCurrency.code')
                    ->label('Destino')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('rate')
                    ->label('Tasa')
                    ->numeric(
                        decimalPlaces: 8,
                        decimalSeparator: '.',
                        thousandsSeparator: ','
                    )
                    ->sortable(),
                TextColumn::make('inverse_rate')
                    ->label('Inversa')
                    ->numeric(
                        decimalPlaces: 8,
                        decimalSeparator: '.',
                        thousandsSeparator: ','
                    )
                    ->sortable(),
                TextColumn::make('effective_date')
                    ->label('Fecha efectiva')
                    ->date()
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Fuente')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'manual' => 'gray',
                        'api' => 'info',
                        'system' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),
                IconColumn::make('is_current')
                    ->label('Actual')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-check-badge')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-clock')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('from_currency_id')
                    ->relationship('fromCurrency', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Moneda origen'),
                SelectFilter::make('to_currency_id')
                    ->relationship('toCurrency', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Moneda destino'),
                SelectFilter::make('is_current')
                    ->options([
                        '1' => 'Actuales',
                        '0' => 'Históricas',
                    ])
                    ->label('Estado'),
            ])
            ->recordActions([
                EditAction::make()
                
                    ->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Editar Tasa de Cambio')
                    ->modalSubmitActionLabel('Actualizar Información')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Tasa actualizada')
                            ->body('La tasa de cambio se ha actualizado correctamente.')
                    ),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Tasa de Cambio')
                    ->modalDescription('¿Estás seguro de eliminar esta tasa de cambio?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Tasa eliminada')
                            ->body('La tasa de cambio se ha eliminado correctamente.')
                    ),
            ])
            ->toolbarActions([
                /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]), */
            ]);
    }
}
