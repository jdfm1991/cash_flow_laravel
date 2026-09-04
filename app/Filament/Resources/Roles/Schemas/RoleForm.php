<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Services\PermissionService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();
        $isSuperAdmin = $user && $user->hasRole('super_admin');

        return $schema
            ->components([
                Section::make('Información del rol')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->label('Nombre del rol')
                            ->placeholder('Ej: admin, accountant, user')
                            ->helperText('El nombre debe ser único y en minúsculas con guiones bajos')
                            ->disabled(fn ($record) => $record && $record->is_system),

                        TextInput::make('guard_name')
                            ->default('web')
                            ->required()
                            ->maxLength(50)
                            ->label('Guard')
                            ->helperText('Usar "web" para Filament y "sanctum" para API')
                            ->disabledOn('edit'),

                        TextInput::make('description')
                            ->maxLength(255)
                            ->label('Descripción')
                            ->placeholder('Breve descripción del rol')
                            ->helperText('Opcional: describe el propósito de este rol')
                            ->disabled(fn ($record) => $record && $record->is_system),
                    ])->columns(2),

                // ✅ Sección de permisos con Select estándar
                static::getPermissionsSection(),

                // ✅ Sección de información adicional (solo en edición)
                static::getRoleInfoSection(),
            ]);
    }

    /**
     * ✅ Sección de permisos - Usando Select estándar con opciones definidas
     */
    protected static function getPermissionsSection()
    {
        // ✅ Obtener opciones de permisos agrupadas
        $groupedPermissions = PermissionService::getGroupedPermissionsForUI();
        $options = [];

        foreach ($groupedPermissions as $group => $data) {
            foreach ($data['permissions'] as $permission) {
                $options[$data['label']][$permission['name']] = $permission['label'];
            }
        }

        return Section::make('Permisos')
            ->description('Selecciona los permisos que tendrá este rol. Los permisos están organizados por módulo.')
            ->schema([
                Select::make('permissions')
                    ->label('Permisos asignados')
                    ->options($options)  // ✅ Definir opciones para validación
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columns(2)
                    ->helperText('Selecciona los permisos que tendrá este rol')
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->permissions->pluck('name')->toArray());
                        }
                    })
                    ->saveRelationshipsUsing(function ($record, $state) {
                        if ($record) {
                            // ✅ No permitir modificar roles del sistema
                            if ($record->is_system) {
                                return;
                            }
                            $record->syncPermissions($state ?? []);
                        }
                    })
                    ->disabled(fn ($record) => $record && $record->is_system),
            ]);
    }

    /**
     * ✅ Sección de información del rol (solo en edición)
     */
    protected static function getRoleInfoSection()
    {
        return Section::make('Información del rol')
            ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord)
            ->schema([
                \Filament\Schemas\Components\View::make('filament.forms.components.role-info'),
            ]);
    }
}