<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Fecha/Hora')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->default('Sistema'),

                TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'create', 'created' => 'success',
                        'update', 'updated' => 'primary',
                        'delete', 'deleted' => 'danger',
                        'login' => 'info',
                        'logout' => 'warning',
                        'view' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'create', 'created' => 'Creación',
                        'update', 'updated' => 'Actualización',
                        'delete', 'deleted' => 'Eliminación',
                        'login' => 'Inicio de sesión',
                        'logout' => 'Cierre de sesión',
                        'view' => 'Visualización',
                        default => ucfirst($state),
                    }),

                TextColumn::make('entity_type')
                    ->label('Entidad')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'Company' => 'Empresa',
                        'User' => 'Usuario',
                        'Bank' => 'Banco',
                        'BankAccount' => 'Cuenta Bancaria',
                        'Currency' => 'Moneda',
                        'ExchangeRate' => 'Tasa de Cambio',
                        'Category' => 'Categoría',
                        'Account' => 'Cuenta Contable',
                        'Transaction' => 'Transacción',
                        'SubscriptionPlan' => 'Plan',
                        default => $state,
                    }),

                TextColumn::make('entity_id')
                    ->label('ID Entidad')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ JSON mejorado en vista previa
                TextColumn::make('new_data')
                    ->label('Cambios')
                    ->limit(50)
                    ->tooltip(function ($record) {
                        $changes = [];
                        if ($record->action === 'create' || $record->action === 'created') {
                            $changes['creado'] = true;
                        }
                        if ($record->action === 'update' || $record->action === 'updated') {
                            $old = $record->old_data ?? [];
                            $new = $record->new_data ?? [];

                            // Mostrar solo los campos que cambiaron
                            foreach ($new as $key => $value) {
                                if (isset($old[$key]) && $old[$key] != $value) {
                                    $changes[$key] = "{$old[$key]} → {$value}";
                                }
                            }
                        }
                        if ($record->action === 'delete' || $record->action === 'deleted') {
                            $changes['eliminado'] = true;
                        }
                        return json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'Sin cambios';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Acción')
                    ->options([
                        'create' => 'Creación',
                        'update' => 'Actualización',
                        'delete' => 'Eliminación',
                        'login' => 'Inicio de sesión',
                        'logout' => 'Cierre de sesión',
                        'view' => 'Visualización',
                    ])
                    ->searchable(),

                SelectFilter::make('entity_type')
                    ->label('Entidad')
                    ->options([
                        'Company' => 'Empresa',
                        'User' => 'Usuario',
                        'Bank' => 'Banco',
                        'BankAccount' => 'Cuenta Bancaria',
                        'Currency' => 'Moneda',
                        'ExchangeRate' => 'Tasa de Cambio',
                        'Category' => 'Categoría',
                        'Account' => 'Cuenta Contable',
                        'Transaction' => 'Transacción',
                        'SubscriptionPlan' => 'Plan',
                    ])
                    ->searchable(),

                SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Usuario'),

                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['start_date'] ?? null, fn($q, $date) =>
                            $q->whereDate('created_at', '>=', $date))
                            ->when($data['end_date'] ?? null, fn($q, $date) =>
                            $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                //EditAction::make(),
            ])
            ->toolbarActions([
                /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]), */
            ]);
    }
}
