<?php

namespace App\Policies;

use App\Models\ExchangeRate;
use App\Models\User;

class ExchangeRatePolicy
{
    // ✅ Ver lista
    public function viewAny(User $user): bool
    {
        // Cualquier usuario autenticado puede ver la lista
        return true;
    }

    // ✅ Ver detalle
    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        // Cualquier usuario autenticado puede ver el detalle
        return true;
    }

    // ✅ Crear
    public function create(User $user): bool
    {
        // Solo super_admin puede crear tasas de cambio
        return $user->hasRole('super_admin');
    }

    // ✅ Editar
    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        // Solo super_admin puede editar tasas de cambio
        return $user->hasRole('super_admin');
    }

    // ✅ Eliminar
    public function delete(User $user, ExchangeRate $exchangeRate): bool
    {
        // Solo super_admin puede eliminar tasas de cambio
        return $user->hasRole('super_admin');
    }

    // ✅ Restaurar (para soft delete)
    public function restore(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->hasRole('super_admin');
    }

    // ✅ Eliminar permanentemente
    public function forceDelete(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->hasRole('super_admin');
    }
}