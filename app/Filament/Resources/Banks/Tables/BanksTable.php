<?php

namespace App\Filament\Resources\Banks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre'),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->label('Código'),
                TextColumn::make('country_name')
                    ->label('País')
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->label('Teléfono'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Activos',
                        '0' => 'Inactivos',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                ->label('Editar')
                ->color('primary')
                ->icon(Heroicon::PencilSquare)
                ->modalHeading('Editar Información Del Banco')
                ->modalSubmitActionLabel('Actualizar Información')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Banco actualizado')
                        ->body('El banco se ha actualizado correctamente.')
                ),
                DeleteAction::make()
                ->label('Eliminar')
                ->color('danger')
                ->icon(Heroicon::Trash)
                ->modalHeading('Eliminar Banco')
                ->modalDescription('Estas seguro de eliminar este banco?')
                ->modalSubmitActionLabel('Si, Eliminar')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Banco eliminado')
                        ->body('El banco se ha eliminado correctamente.')
                )
            ])
            ->toolbarActions([
                //BulkActionGroup::make([
                //    DeleteBulkAction::make(),
                //]),
            ]);
    }
}
