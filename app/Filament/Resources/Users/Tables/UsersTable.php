<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=FFFFFF&background=4F46E5'),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copiado'),

                TextColumn::make('companies.name')
                    ->label('Empresas')
                    ->badge()
                    ->color('primary')
                    ->separator(',')
                    ->limit(3),

                TextColumn::make('roles')
                    ->label('Roles')
                    ->badge()
                    ->color('gray')
                    ->separator(',')
                    ->state(fn ($record) => $record->roles->pluck('name')->toArray()),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('email_verified')
                    ->label('Verificado')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),
            ])
            ->filters([
                // Filtros personalizados
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Editar Usuario')
                    ->modalSubmitActionLabel('Actualizar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Usuario actualizado')
                            ->body('El usuario se ha actualizado correctamente.')
                    ),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Usuario')
                    ->modalDescription('¿Estás seguro de eliminar este usuario?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Usuario eliminado')
                            ->body('El usuario se ha eliminado correctamente.')
                    )
                    ->before(function ($record) {
                        // ✅ No permitir eliminar al propio usuario
                        if ($record->id === Auth::id()) {
                            throw new \Exception('No puedes eliminar tu propio usuario.');
                        }

                        // ✅ No permitir eliminar al Super Admin
                        if ($record->hasRole('super_admin')) {
                            throw new \Exception('No se puede eliminar al Super Administrador.');
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->id === Auth::id()) {
                                    throw new \Exception('No puedes eliminar tu propio usuario.');
                                }
                                if ($record->hasRole('super_admin')) {
                                    throw new \Exception('No se puede eliminar al Super Administrador.');
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}