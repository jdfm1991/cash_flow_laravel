<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre')
                    ->description(fn($record) => $record->getPathAttribute())
                    ->formatStateUsing(
                        fn($state, $record) =>
                        str_repeat('— ', $record->level ?? 0) . $state
                    ),
                ColorColumn::make('color')
                    ->label('Color'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn($state) => $state === 'income' ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => $state === 'income' ? 'Ingreso' : 'Egreso')
                    ->label('Tipo'),
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('parent.name')
                    ->label('Padre')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_system')
                    ->label('Sistema')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'income' => 'Ingresos',
                        'expense' => 'Egresos',
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
                    ->modalHeading('Editar Categoría')
                    ->modalSubmitActionLabel('Actualizar Información')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Categoría actualizada')
                            ->body('La categoría se ha actualizado correctamente.')
                    ),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Categoría')
                    ->modalDescription('¿Estás seguro de eliminar esta categoría?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Categoría eliminada')
                            ->body('La categoría se ha eliminado correctamente.')
                    )
                    ->before(function ($record) {
                        if ($record->is_system) {
                            throw new \Exception('No se puede eliminar una categoría del sistema.');
                        }
                        if ($record->children()->count() > 0) {
                            throw new \Exception('No se puede eliminar una categoría que tiene subcategorías.');
                        }
                        if ($record->accounts()->count() > 0) {
                            throw new \Exception('No se puede eliminar una categoría que tiene cuentas asociadas.');
                        }
                        if ($record->transactions()->count() > 0) {
                            throw new \Exception('No se puede eliminar una categoría que tiene transacciones asociadas.');
                        }
                    }),
            ])
            ->toolbarActions([
                /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]), */
            ]);
    }
}
