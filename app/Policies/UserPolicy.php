<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Context\CompanyContext;

class UserPolicy
{
    /**
     * Obtener el contexto de empresa
     */
    protected function getCompanyContext(): CompanyContext
    {
        return app(CompanyContext::class);
    }

    /**
     * Verificar si el usuario pertenece a la empresa actual
     */
    protected function belongsToCurrentCompany(User $user): bool
    {
        $context = $this->getCompanyContext();
        $companyId = $context->getCurrentCompanyId();

        if (!$companyId) {
            return false;
        }

        return $user->companies()->where('companies.id', $companyId)->exists();
    }

    // ✅ Ver lista
    public function viewAny(User $user): bool
    {
        // Super_admin puede ver todos
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Admin puede ver usuarios de su empresa
        if ($user->hasRole('admin')) {
            return $this->belongsToCurrentCompany($user);
        }

        // Usuario normal solo puede verse a sí mismo
        return false;
    }

    // ✅ Ver detalle
    public function view(User $user, User $model): bool
    {
        // Super_admin puede ver todos
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Un usuario puede ver su propio perfil
        if ($user->id === $model->id) {
            return true;
        }

        // Admin puede ver usuarios de su empresa
        if ($user->hasRole('admin')) {
            return $this->belongsToCurrentCompany($model);
        }

        return false;
    }

    // ✅ Crear
    public function create(User $user): bool
    {
        // Solo super_admin puede crear usuarios
        return $user->hasRole('super_admin');
    }

    // ✅ Editar
    public function update(User $user, User $model): bool
    {
        // Super_admin puede editar todos
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Un usuario puede editar su propio perfil
        if ($user->id === $model->id) {
            return true;
        }

        // Admin puede editar usuarios de su empresa
        if ($user->hasRole('admin')) {
            return $this->belongsToCurrentCompany($model);
        }

        return false;
    }

    // ✅ Eliminar
    public function delete(User $user, User $model): bool
    {
        // No se puede eliminar a sí mismo
        if ($user->id === $model->id) {
            return false;
        }

        // Solo super_admin puede eliminar usuarios
        return $user->hasRole('super_admin');
    }

    // ✅ Restaurar (para soft delete)
    public function restore(User $user, User $model): bool
    {
        return $user->hasRole('super_admin');
    }

    // ✅ Eliminar permanentemente
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('super_admin');
    }
}