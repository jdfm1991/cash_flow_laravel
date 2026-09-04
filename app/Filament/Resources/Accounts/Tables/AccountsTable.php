<?php

namespace App\Filament\Resources\Accounts\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre'),
                TextColumn::make('category.name')
                    ->searchable()
                    ->sortable()
                    ->label('Categoría'),
                TextColumn::make('category.type')
                    ->badge()
                    ->color(fn($state) => $state === 'income' ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => $state === 'income' ? 'Ingreso' : 'Egreso')
                    ->label('Tipo'),
                TextColumn::make('codigo_contable')
                    ->label('Código contable')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
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
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Categoría'),
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
                    ->modalHeading('Editar Cuenta Contable')
                    ->modalSubmitActionLabel('Actualizar Información')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cuenta actualizada')
                            ->body('La cuenta contable se ha actualizado correctamente.')
                    ),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Cuenta Contable')
                    ->modalDescription('¿Estás seguro de eliminar esta cuenta contable?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cuenta eliminada')
                            ->body('La cuenta contable se ha eliminado correctamente.')
                    )
                    ->before(function ($record) {
                        if ($record->is_system) {
                            throw new \Exception('No se puede eliminar una cuenta del sistema.');
                        }
                        if ($record->transactions()->count() > 0) {
                            throw new \Exception('No se puede eliminar una cuenta que tiene transacciones asociadas.');
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
