<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Models\Role;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->is_system ? 'warning' : 'primary')
                    ->formatStateUsing(fn ($state, $record) => $record->is_system ? $state . ' 🔒' : $state),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->toggleable()
                    ->placeholder('Sin descripción'),

                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->sortable()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => $state . ' permisos'),

                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->sortable()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state . ' usuarios'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                // ✅ Filtro para mostrar roles del sistema
                \Filament\Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'system' => 'Sistema 🔒',
                        'custom' => 'Personalizados',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'system') {
                            return $query->where('is_system', true);
                        }
                        if ($data['value'] === 'custom') {
                            return $query->where('is_system', false);
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading(fn ($record) => $record->is_system ? 'Editar Rol del Sistema' : 'Editar Rol')
                    ->modalSubmitActionLabel('Actualizar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Rol actualizado')
                            ->body('El rol se ha actualizado correctamente.')
                    ),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Rol')
                    ->modalDescription('¿Estás seguro de eliminar este rol?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Rol eliminado')
                            ->body('El rol se ha eliminado correctamente.')
                    )
                    ->before(function ($record) {
                        // ✅ No permitir eliminar roles del sistema
                        if ($record->is_system) {
                            throw new \Exception('No se puede eliminar un rol del sistema.');
                        }
                        
                        // ✅ No permitir eliminar si tiene usuarios
                        if ($record->users()->count() > 0) {
                            throw new \Exception('No se puede eliminar un rol que tiene usuarios asignados.');
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->is_system) {
                                    throw new \Exception('No se puede eliminar roles del sistema.');
                                }
                                if ($record->users()->count() > 0) {
                                    throw new \Exception('No se puede eliminar roles con usuarios asignados.');
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}