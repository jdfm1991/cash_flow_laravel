<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Definición de todos los permisos del sistema agrupados por módulo
     */
    public static function getPermissionDefinitions(): array
    {
        return [
            // Administración
            'users' => ['label' => 'Usuarios', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'roles' => ['label' => 'Roles', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'companies' => ['label' => 'Empresas', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'audit' => ['label' => 'Auditoría', 'permissions' => ['view']],

            // Catálogos
            'banks' => ['label' => 'Bancos', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'bank_accounts' => ['label' => 'Cuentas Bancarias', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'currencies' => ['label' => 'Monedas', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'exchange_rates' => ['label' => 'Tasas de Cambio', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'categories' => ['label' => 'Categorías', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'accounts' => ['label' => 'Cuentas Contables', 'permissions' => ['view', 'create', 'edit', 'delete']],

            // Transacciones
            'transactions' => ['label' => 'Transacciones', 'permissions' => ['view', 'create', 'edit', 'delete']],

            // Reportes
            'reports' => ['label' => 'Reportes', 'permissions' => ['view', 'export']],

            // Importaciones
            'imports' => ['label' => 'Importaciones', 'permissions' => ['view', 'import']],
        ];
    }

    /**
     * Obtener todos los permisos agrupados para UI
     */
    public static function getGroupedPermissionsForUI(): array
    {
        return Cache::remember('permissions_grouped_ui', 3600, function () {
            $definitions = self::getPermissionDefinitions();
            $grouped = [];

            foreach ($definitions as $group => $data) {
                $grouped[$group] = [
                    'label' => $data['label'],
                    'permissions' => array_map(function ($action) use ($group) {
                        return [
                            'name' => $group . '_' . $action,
                            'label' => self::getActionLabel($action),
                            'action' => $action,
                        ];
                    }, $data['permissions']),
                ];
            }

            return $grouped;
        });
    }

    /**
     * Obtener etiqueta para una acción
     */
    public static function getActionLabel(string $action): string
    {
        $labels = [
            'view' => 'Ver',
            'create' => 'Crear',
            'edit' => 'Editar',
            'delete' => 'Eliminar',
            'export' => 'Exportar',
            'import' => 'Importar',
        ];

        return $labels[$action] ?? ucfirst($action);
    }

    /**
     * Obtener color para una acción
     */
    public static function getActionColor(string $action): string
    {
        $colors = [
            'view' => 'info',
            'create' => 'success',
            'edit' => 'warning',
            'delete' => 'danger',
            'export' => 'primary',
            'import' => 'success',
        ];

        return $colors[$action] ?? 'gray';
    }

    /**
     * Obtener icono para una acción
     */
    public static function getActionIcon(string $action): string
    {
        $icons = [
            'view' => 'heroicon-o-eye',
            'create' => 'heroicon-o-plus-circle',
            'edit' => 'heroicon-o-pencil-square',
            'delete' => 'heroicon-o-trash',
            'export' => 'heroicon-o-arrow-down-tray',
            'import' => 'heroicon-o-arrow-up-tray',
        ];

        return $icons[$action] ?? 'heroicon-o-check';
    }

    /**
     * Obtener permisos para Select (formateados con grupos)
     */
    public static function getPermissionsForSelect(): array
    {
        $grouped = self::getGroupedPermissionsForUI();
        $options = [];

        foreach ($grouped as $group => $data) {
            foreach ($data['permissions'] as $permission) {
                $options[$data['label']][$permission['name']] = $permission['label'];
            }
        }

        return $options;
    }

    /**
     * Obtener permisos con su información completa
     */
    public static function getPermissionsWithInfo(): Collection
    {
        return Cache::remember('permissions_with_info', 3600, function () {
            $permissions = Permission::orderBy('name')->get();
            $grouped = self::getGroupedPermissionsForUI();

            return $permissions->map(function ($permission) use ($grouped) {
                // Extraer grupo y acción del nombre
                $parts = explode('_', $permission->name);
                $action = array_pop($parts);
                $group = implode('_', $parts);

                // Buscar información del grupo
                $groupInfo = $grouped[$group] ?? null;

                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'group' => $group,
                    'group_label' => $groupInfo['label'] ?? $group,
                    'action' => $action,
                    'action_label' => self::getActionLabel($action),
                    'action_color' => self::getActionColor($action),
                    'action_icon' => self::getActionIcon($action),
                ];
            });
        });
    }

    /**
     * Sincronizar permisos del sistema
     */
    public static function syncPermissions(): void
    {
        $definitions = self::getPermissionDefinitions();

        foreach ($definitions as $group => $data) {
            foreach ($data['permissions'] as $action) {
                $name = $group . '_' . $action;  // Formato: users_view, roles_view, etc.
                Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
            }
        }

        Cache::forget('permissions_grouped_ui');
        Cache::forget('permissions_with_info');
        Cache::forget('permissions_all');
    }

    /**
     * Invalidar caché de permisos
     */
    public static function clearCache(): void
    {
        Cache::forget('permissions_grouped_ui');
        Cache::forget('permissions_with_info');
        Cache::forget('permissions_all');
    }

    /**
     * Obtener el total de permisos
     */
    public static function getTotalPermissions(): int
    {
        return Permission::count();
    }
}
