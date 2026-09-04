<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    // ✅ Ver lista
    public function viewAny(User $user): bool
    {
        return true;  // Cualquier usuario autenticado puede ver la lista
    }

    // ✅ Ver detalle
    public function view(User $user, Currency $currency): bool
    {
        return true;  // Cualquier usuario autenticado puede ver el detalle
    }

    // ✅ Crear
    public function create(User $user): bool
    {
        // Solo super_admin puede crear monedas
        return $user->hasRole('super_admin');
    }

    // ✅ Editar
    public function update(User $user, Currency $currency): bool
    {
        // Solo super_admin puede editar monedas
        return $user->hasRole('super_admin');
    }

    // ✅ Eliminar
    public function delete(User $user, Currency $currency): bool
    {
        // Solo super_admin puede eliminar monedas
        if ($currency->is_base) {
            return false;  // No se puede eliminar la moneda base
        }
        return $user->hasRole('super_admin');
    }

    // ✅ Restaurar (para soft delete)
    public function restore(User $user, Currency $currency): bool
    {
        return $user->hasRole('super_admin');
    }

    // ✅ Eliminar permanentemente
    public function forceDelete(User $user, Currency $currency): bool
    {
        return $user->hasRole('super_admin');
    }
}