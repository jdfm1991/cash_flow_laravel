<?php

namespace App\Services;

use App\Models\Role;
use App\Services\Context\CompanyContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RoleService
{
    /**
     * Obtener el contexto de empresa
     */
    protected function getCompanyContext(): CompanyContext
    {
        return app(CompanyContext::class);
    }

    /**
     * Obtener todos los roles disponibles
     * ✅ Corregido: Usar Cache::remember con serialización segura
     */
    public function getAllRoles(): Collection
    {
        // ✅ Usar Cache::remember con una función que devuelva Collection
        return Cache::remember('roles_all', 3600, function () {
            // ✅ Forzar la carga de roles desde la base de datos
            return Role::where('guard_name', 'web')
                ->with('permissions')  // Cargar permisos para evitar N+1
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Obtener roles para el Select (formateados)
     */
    public function getRolesForSelect(): array
    {
        $roles = $this->getAllRoles();
        $options = [];

        foreach ($roles as $role) {
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
     * Obtener roles agrupados por tipo
     */
    public function getRolesGrouped(): array
    {
        $roles = $this->getAllRoles();
        
        return [
            'system' => $roles->filter(fn($r) => $r->is_system)->pluck('name', 'name')->toArray(),
            'custom' => $roles->filter(fn($r) => !$r->is_system)->pluck('name', 'name')->toArray(),
        ];
    }

    /**
     * Obtener roles con sus permisos para mostrar
     */
    public function getRolesWithPermissions(): Collection
    {
        return $this->getAllRoles()->map(function ($role) {
            return [
                'name' => $role->name,
                'label' => $role->name,
                'is_system' => $role->is_system,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'permission_count' => $role->permissions->count(),
                'users_count' => $role->users()->count(),
                'description' => $role->description ?? $this->getDefaultDescription($role->name),
            ];
        });
    }

    /**
     * Obtener descripción por defecto para roles comunes
     */
    protected function getDefaultDescription(string $roleName): string
    {
        $descriptions = [
            'super_admin' => 'Acceso total al sistema. No editable.',
            'admin' => 'Gestión completa de la empresa. Puede administrar usuarios y configuraciones.',
            'accountant' => 'Acceso a módulos contables y financieros.',
            'user' => 'Acceso básico para operaciones diarias.',
            'viewer' => 'Solo visualización de datos. Sin capacidad de edición.',
        ];

        return $descriptions[$roleName] ?? 'Rol personalizado';
    }

    /**
     * Invalidar caché de roles
     */
    public function clearCache(): void
    {
        Cache::forget('roles_all');
    }

    /**
     * ✅ Nuevo: Obtener roles directamente sin caché (para evitar problemas de serialización)
     */
    public function getRolesDirectly(): Collection
    {
        return Role::where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    /**
     * ✅ Nuevo: Obtener roles para Select sin caché
     */
    public function getRolesForSelectDirect(): array
    {
        $roles = $this->getRolesDirectly();
        $options = [];

        foreach ($roles as $role) {
            $label = $role->name;
            
            if ($role->is_system) {
                $label .= ' 🔒';
            }

            $permissionCount = $role->permissions->count();
            if ($permissionCount > 0) {
                $label .= " ({$permissionCount} permisos)";
            }

            $options[$role->name] = $label;
        }

        return $options;
    }
}