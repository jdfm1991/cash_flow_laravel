<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Account;
use Illuminate\Auth\Access\Response;

class AccountPolicy
{
    /**
     * Determinar si el usuario puede ver la lista de cuentas
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determinar si el usuario puede ver una cuenta específica
     */
    public function view(User $user, Account $account): bool
    {
        return true;
    }

    /**
     * Determinar si el usuario puede crear cuentas
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determinar si el usuario puede actualizar una cuenta
     */
    public function update(User $user, Account $account): bool
    {
        // No permitir editar cuentas del sistema
        if ($account->is_system) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    /**
     * Determinar si el usuario puede eliminar una cuenta
     */
    public function delete(User $user, Account $account): bool
    {
        // No permitir eliminar cuentas del sistema
        if ($account->is_system) {
            return false;
        }

        // No permitir eliminar si tiene transacciones asociadas
        if ($account->transactions()->count() > 0) {
            return false;
        }

        return $user->hasRole('super_admin');
    }
}