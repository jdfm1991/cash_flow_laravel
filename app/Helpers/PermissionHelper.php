<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PermissionHelper
{
    /**
     * Verificar si un usuario tiene un permiso específico
     * ✅ Versión mejorada con caché para evitar consultas repetidas
     */
    public static function userCan(User $user, string $permission): bool
    {
        // Verificar si el usuario es super_admin
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Obtener IDs de los roles del usuario
        $roleIds = $user->roles()->pluck('id')->toArray();
        
        if (empty($roleIds)) {
            return false;
        }

        // Obtener el ID del permiso
        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permissionId) {
            return false;
        }

        // Verificar si el permiso está asignado a alguno de los roles
        return DB::table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->where('permission_id', $permissionId)
            ->exists();
    }

    /**
     * Obtener todos los permisos de un usuario
     */
    public static function getUserPermissions(User $user): array
    {
        // Si es super_admin, devolver todos los permisos
        if ($user->hasRole('super_admin')) {
            return DB::table('permissions')
                ->where('guard_name', 'web')
                ->pluck('name')
                ->toArray();
        }

        $roleIds = $user->roles()->pluck('id')->toArray();
        
        if (empty($roleIds)) {
            return [];
        }

        return DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('role_has_permissions.role_id', $roleIds)
            ->where('permissions.guard_name', 'web')
            ->pluck('permissions.name')
            ->unique()
            ->toArray();
    }
}