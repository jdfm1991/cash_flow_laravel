<?php

namespace App\Filament\Resources\Currencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CurrenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('symbol')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('decimal_places')
                    ->sortable(),
                IconColumn::make('is_base')
                    ->label('Base')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Editar Información Del Moneda')
                    ->modalSubmitActionLabel('Actualizar Información')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Moneda actualizado')
                            ->body('El Moneda se ha actualizado correctamente.')
                    ),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Moneda')
                    ->modalDescription('Estas seguro de eliminar este Moneda?')
                    ->modalSubmitActionLabel('Si, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Moneda eliminado')
                            ->body('El Moneda se ha eliminado correctamente.')
                    )
            ])
            ->toolbarActions([
                /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]), */
            ]);
    }
}
