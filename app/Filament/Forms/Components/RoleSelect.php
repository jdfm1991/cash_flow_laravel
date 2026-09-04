<?php

namespace App\Filament\Forms\Components;

use App\Models\Role;
use Closure;
use Filament\Forms\Components\Select;

class RoleSelect extends Select
{
    protected bool|Closure $hideSystemRoles = false;

    public function hideSystemRoles(bool|Closure $condition = true): static
    {
        $this->hideSystemRoles = $condition;
        return $this;
    }

    /**
     * ✅ Obtener opciones para validación y visualización
     */
    public function getOptions(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->hasRole('super_admin');

        $roles = Role::where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $options = [];

        foreach ($roles as $role) {
            // ✅ Ocultar roles del sistema si no es super_admin
            if ($this->evaluate($this->hideSystemRoles) && $role->is_system && !$isSuperAdmin) {
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

        return $options;
    }

    /**
     * ✅ Configurar el campo
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable()
            ->preload()
            ->multiple()
            ->helperText('Selecciona los roles del usuario. Los roles con 🔒 son del sistema.');
    }

    /**
     * ✅ Método make con firma correcta
     */
    public static function make(?string $name = null): static
    {
        $static = parent::make($name);
        
        // ✅ Las opciones se configuran en getOptions()
        // No es necesario llamar a options() aquí
        
        return $static;
    }
}