<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Bank;
use Illuminate\Auth\Access\Response;

class BankPolicy
{
    /**
     * Determine if the user can view any banks.
     */
    public function viewAny(User $user): bool
    {
        return true; // Público
    }

    /**
     * Determine if the user can view a specific bank.
     */
    public function view(User $user, Bank $bank): bool
    {
        return true; // Público
    }

    /**
     * Determine if the user can create banks.
     */
    public function create(User $user): bool
    {
        // ✅ Solo super_admin puede crear bancos
        return $user->hasRole('super_admin');
    }

    /**
     * Determine if the user can update a bank.
     */
    public function update(User $user, Bank $bank): bool
    {
        // ✅ Solo super_admin puede actualizar bancos
        return $user->hasRole('super_admin');
    }

    /**
     * Determine if the user can delete a bank.
     */
    public function delete(User $user, Bank $bank): bool
    {
        // ✅ Solo super_admin puede eliminar bancos
        return $user->hasRole('super_admin');
    }

    /**
     * Determine if the user can restore a bank.
     */
    public function restore(User $user, Bank $bank): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine if the user can permanently delete a bank.
     */
    public function forceDelete(User $user, Bank $bank): bool
    {
        return $user->hasRole('super_admin');
    }
}