<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Services\Context\CompanyContext;
use App\Models\Role;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información personal')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->label('Nombre completo')
                            ->placeholder('Ej: Juan Pérez'),

                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->label('Email'),

                        FileUpload::make('avatar_path')
                            ->image()
                            ->directory('avatars')
                            ->visibility('public')
                            ->label('Avatar')
                            ->helperText('Imagen de perfil (recomendado 200x200)'),
                    ])->columns(2),

                Section::make('Credenciales')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->maxLength(255)
                            ->minLength(8)
                            ->label('Contraseña')
                            ->helperText('Mínimo 8 caracteres. Dejar en blanco para mantener la actual.')
                            ->revealable()
                            // ✅ CORREGIDO: Solo procesar si hay valor
                            ->dehydrateStateUsing(
                                fn ($state) => filled($state) ? bcrypt($state) : null
                            )
                            ->required(function ($livewire) {
                                if (method_exists($livewire, 'getRecord') && $livewire->getRecord()) {
                                    return false;
                                }
                                return $livewire instanceof \Filament\Resources\Pages\CreateRecord;
                            })
                            ->nullable(),

                        TextInput::make('password_confirmation')
                            ->password()
                            ->label('Confirmar contraseña')
                            ->same('password')
                            ->revealable()
                            ->required(function ($livewire) {
                                if (method_exists($livewire, 'getRecord') && $livewire->getRecord()) {
                                    return false;
                                }
                                return $livewire instanceof \Filament\Resources\Pages\CreateRecord;
                            })
                            ->nullable(),
                    ])->columns(2),

                Section::make('Empresas y roles')
                    ->schema([
                        static::getCompaniesField(),
                        static::getRolesField(),
                    ])->columns(2),

                Section::make('Estado')
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Usuario activo'),

                        Toggle::make('email_verified')
                            ->label('Email verificado')
                            ->helperText('Marcar si el email ya fue verificado')
                            ->default(false),

                        static::getUserInfoField(),
                    ])->columns(2),
            ])
            ->columns(1);
    }

    /**
     * Campo de empresas con contexto
     */
    protected static function getCompaniesField()
    {
        return Select::make('companies')
            ->label('Empresas')
            ->relationship('companies', 'name')
            ->multiple()
            ->required()
            ->searchable()
            ->preload()
            ->helperText('Selecciona las empresas a las que pertenece el usuario')
            ->saveRelationshipsUsing(function ($record, $state) {
                if ($record) {
                    $record->companies()->sync($state ?? []);
                    
                    if (auth()->id() === $record->id) {
                        $context = app(CompanyContext::class);
                        $currentCompanyId = $context->getCurrentCompanyId();
                        
                        if ($currentCompanyId && !in_array($currentCompanyId, $state ?? [])) {
                            $newDefault = $state[0] ?? null;
                            if ($newDefault) {
                                $context->setCurrentCompany($newDefault);
                            }
                        }
                    }
                }
            })
            ->afterStateHydrated(function ($component, $record) {
                if ($record) {
                    $component->state($record->companies->pluck('id')->toArray());
                }
            });
    }

    /**
     * ✅ Campo de roles - Usando Select estándar
     */
    protected static function getRolesField()
    {
        $user = Auth::user();
        $isSuperAdmin = $user && $user->hasRole('super_admin');

        // ✅ Obtener opciones de roles directamente
        $roles = Role::where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $options = [];
        foreach ($roles as $role) {
            // ✅ Ocultar roles del sistema si no es super_admin
            if (!$isSuperAdmin && $role->is_system) {
                continue;
            }

            $label = $role->name;
            if ($role->is_system) {
                $label .= ' 🔒';
            }

            $permissionCount = $role->permissions()->count();
            if ($permissionCount > 0) {
                $label .= " ({$permissionCount} permisos)";
            }

            $options[$role->name] = $label;
        }

        return Select::make('roles')
            ->label('Roles')
            ->options($options)
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Selecciona los roles del usuario. Los roles con 🔒 son del sistema.')
            ->saveRelationshipsUsing(function ($record, $state) {
                if ($record) {
                    // ✅ Validar que no se asignen roles de sistema si no es super_admin
                    $user = Auth::user();
                    if (!$user || !$user->hasRole('super_admin')) {
                        $state = array_filter($state, function ($roleName) {
                            $role = Role::where('name', $roleName)->first();
                            return $role && !$role->is_system;
                        });
                    }
                    
                    $record->syncRoles($state ?? []);
                }
            })
            ->afterStateHydrated(function ($component, $record) {
                if ($record) {
                    $component->state($record->roles->pluck('name')->toArray());
                }
            });
    }

    /**
     * Información adicional del usuario (solo en edición)
     */
    protected static function getUserInfoField()
    {
        return \Filament\Schemas\Components\View::make('filament.forms.components.user-info')
            ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord);
    }
}