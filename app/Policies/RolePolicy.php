<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Context\CompanyContext;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Obtener el contexto de empresa
     */
    protected function getCompanyContext(): CompanyContext
    {
        return app(CompanyContext::class);
    }

    // ✅ Ver lista
    public function viewAny(User $user): bool
    {
        // Solo super_admin puede ver roles
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Admin puede ver roles (pero no editarlos)
        if ($user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    // ✅ Ver detalle
    public function view(User $user, Role $role): bool
    {
        // Solo super_admin puede ver roles
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Admin puede ver roles (pero no editarlos)
        if ($user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    // ✅ Crear
    public function create(User $user): bool
    {
        // Solo super_admin puede crear roles
        return $user->hasRole('super_admin');
    }

    // ✅ Editar
    public function update(User $user, Role $role): bool
    {
        // Solo super_admin puede editar roles
        if (!$user->hasRole('super_admin')) {
            return false;
        }

        // No se puede modificar el rol super_admin
        if ($role->name === 'super_admin') {
            return false;
        }

        return true;
    }

    // ✅ Eliminar
    public function delete(User $user, Role $role): bool
    {
        // Solo super_admin puede eliminar roles
        if (!$user->hasRole('super_admin')) {
            return false;
        }

        // No se puede eliminar el rol super_admin
        if ($role->name === 'super_admin') {
            return false;
        }

        // No se puede eliminar un rol con usuarios asignados
        if ($role->users()->count() > 0) {
            return false;
        }

        return true;
    }

    // ✅ Restaurar (para soft delete)
    public function restore(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin');
    }

    // ✅ Eliminar permanentemente
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin');
    }
}