<?php

namespace App\Filament\Resources\SubscriptionPlans\Tables;

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

class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre'),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->label('Slug'),
                TextColumn::make('max_users')
                    ->sortable()
                    ->label('Usuarios')
                    ->formatStateUsing(fn($state) => $state >= 999 ? '∞' : $state),
                TextColumn::make('max_bank_accounts')
                    ->sortable()
                    ->label('Cuentas')
                    ->formatStateUsing(fn($state) => $state >= 999 ? '∞' : $state),
                TextColumn::make('price')
                    ->sortable()
                    ->money(fn($record) => $record->currency?->code ?? 'USD')
                    ->label('Precio'),
                TextColumn::make('currency.code')
                    ->label('Moneda')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
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
                EditAction::make()->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Editar Plan de Suscripción')
                    ->modalSubmitActionLabel('Actualizar Información')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Plan actualizado')
                            ->body('El plan de suscripción se ha actualizado correctamente.')
                    ),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Plan')
                    ->modalDescription('¿Estás seguro de eliminar este plan de suscripción?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Plan eliminado')
                            ->body('El plan de suscripción se ha eliminado correctamente.')
                    )
                    ->before(function ($record) {
                        // ❌ No permitir eliminar el plan Free si está en uso
                        if ($record->slug === 'free' && $record->companies()->exists()) {
                            throw new \Exception('No se puede eliminar el plan Free porque tiene empresas asociadas.');
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
