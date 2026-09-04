<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Category;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    /**
     * Determinar si el usuario puede ver la lista de categorías
     */
    public function viewAny(User $user): bool
    {
        return true; // Todos los usuarios autenticados pueden ver categorías
    }

    /**
     * Determinar si el usuario puede ver una categoría específica
     */
    public function view(User $user, Category $category): bool
    {
        return true;
    }

    /**
     * Determinar si el usuario puede crear categorías
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determinar si el usuario puede actualizar una categoría
     */
    public function update(User $user, Category $category): bool
    {
        // No permitir editar categorías del sistema
        if ($category->is_system) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    /**
     * Determinar si el usuario puede eliminar una categoría
     */
    public function delete(User $user, Category $category): bool
    {
        // No permitir eliminar categorías del sistema
        if ($category->is_system) {
            return false;
        }

        // No permitir eliminar si tiene cuentas asociadas
        if ($category->accounts()->count() > 0) {
            return false;
        }

        return $user->hasRole('super_admin');
    }
}