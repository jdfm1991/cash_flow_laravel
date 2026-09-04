<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    /**
     * Determinar si el usuario puede ver la lista de transacciones
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || 
               $user->hasRole('admin') || 
               $user->hasRole('accountant') || 
               $user->hasRole('user');
    }

    /**
     * Determinar si el usuario puede ver una transacción específica
     */
    public function view(User $user, Transaction $transaction): bool
    {
        // Super admin puede ver todas las transacciones
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Los demás solo pueden ver transacciones de su empresa
        return $user->current_company_id === $transaction->company_id;
    }

    /**
     * Determinar si el usuario puede crear transacciones
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || 
               $user->hasRole('admin') || 
               $user->hasRole('accountant');
    }

    /**
     * Determinar si el usuario puede actualizar una transacción
     */
    public function update(User $user, Transaction $transaction): bool
    {
        // Super admin puede actualizar cualquier transacción
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Admin y accountant solo pueden actualizar transacciones de su empresa
        if ($user->hasRole('admin') || $user->hasRole('accountant')) {
            return $user->current_company_id === $transaction->company_id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede eliminar una transacción
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        // Super admin puede eliminar cualquier transacción
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Admin solo puede eliminar transacciones de su empresa
        if ($user->hasRole('admin')) {
            return $user->current_company_id === $transaction->company_id && 
                   !$transaction->is_reconciled;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede conciliar una transacción
     */
    public function reconcile(User $user, Transaction $transaction): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            return $user->current_company_id === $transaction->company_id;
        }

        return false;
    }
}