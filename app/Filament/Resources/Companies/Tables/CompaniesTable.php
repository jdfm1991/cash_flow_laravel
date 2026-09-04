<?php

namespace App\Filament\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre comercial')
                    ->description(fn ($record) => $record->business_name),
                TextColumn::make('tax_id')
                    ->searchable()
                    ->sortable()
                    ->label('RIF / NIT')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('subscriptionPlan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Usuarios')
                    ->sortable(),
                TextColumn::make('bank_accounts_count')
                    ->counts('bankAccounts')
                    ->label('Cuentas')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Creada')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                 SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Activas',
                        '0' => 'Inactivas',
                    ]),
                SelectFilter::make('subscription_plan_id')
                    ->relationship('subscriptionPlan', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Plan'),
            ])
            ->recordActions([
                EditAction::make()
                ->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Editar Empresa')
                    ->modalSubmitActionLabel('Actualizar Información')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Empresa actualizada')
                            ->body('La empresa se ha actualizado correctamente.')
                    ),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Empresa')
                    ->modalDescription('¿Estás seguro de eliminar esta empresa? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Empresa eliminada')
                            ->body('La empresa se ha eliminado correctamente.')
                    )
                    ->before(function ($record) {
                        if ($record->transactions()->count() > 0) {
                            throw new \Exception('No se puede eliminar una empresa que tiene transacciones asociadas.');
                        }
                        if ($record->users()->count() > 0) {
                            throw new \Exception('No se puede eliminar una empresa que tiene usuarios activos.');
                        }
                    }),
            ])
            ->toolbarActions([
                /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]), */
            ]);
    }
}
