<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Company;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    /**
     * Determinar si el usuario puede ver la lista de empresas
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('admin');
    }

    /**
     * Determinar si el usuario puede ver una empresa específica
     */
    public function view(User $user, Company $company): bool
    {
        // Super admin y admin pueden ver todas las empresas
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return true;
        }

        // Usuarios normales solo pueden ver su propia empresa
        return $user->current_company_id === $company->id;
    }

    /**
     * Determinar si el usuario puede crear empresas
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determinar si el usuario puede actualizar una empresa
     */
    public function update(User $user, Company $company): bool
    {
        // Super admin puede actualizar cualquier empresa
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Admin solo puede actualizar su propia empresa
        if ($user->hasRole('admin')) {
            return $user->current_company_id === $company->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede eliminar una empresa
     */
    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determinar si el usuario puede restaurar una empresa eliminada
     */
    public function restore(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente una empresa
     */
    public function forceDelete(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determinar si el usuario puede cambiar la suscripción de una empresa
     */
    public function changeSubscription(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }
}