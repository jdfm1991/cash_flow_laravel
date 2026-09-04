<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BankAccount;
use Illuminate\Auth\Access\Response;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || 
               $user->hasRole('admin') || 
               $user->hasRole('accountant');
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->current_company_id === $bankAccount->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('admin');
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            return $user->current_company_id === $bankAccount->company_id;
        }

        return false;
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            return $user->current_company_id === $bankAccount->company_id && 
                   $bankAccount->transactions()->count() === 0;
        }

        return false;
    }
}