<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * ✅ Definición completa de permisos (incluyendo nuevos módulos)
     */
    public static function getPermissionDefinitions(): array
    {
        return [
            // ============================================================
            // ADMINISTRACIÓN
            // ============================================================
            'users' => ['label' => 'Usuarios', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'roles' => ['label' => 'Roles', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'companies' => ['label' => 'Empresas', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'audit' => ['label' => 'Auditoría', 'permissions' => ['view']],

            // ============================================================
            // CATÁLOGOS
            // ============================================================
            'banks' => ['label' => 'Bancos', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'bank_accounts' => ['label' => 'Cuentas Bancarias', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'currencies' => ['label' => 'Monedas', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'exchange_rates' => ['label' => 'Tasas de Cambio', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'categories' => ['label' => 'Categorías', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'accounts' => ['label' => 'Cuentas Contables', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'subscription_plans' => ['label' => 'Planes de Suscripción', 'permissions' => ['view', 'create', 'edit', 'delete']],

            // ============================================================
            // TRANSACCIONES
            // ============================================================
            'transactions' => ['label' => 'Transacciones', 'permissions' => ['view', 'create', 'edit', 'delete']],

            // ============================================================
            // IMPORTACIONES (NUEVOS MÓDULOS)
            // ============================================================
            'import_sessions' => ['label' => 'Sesiones de Importación', 'permissions' => ['view', 'create', 'edit', 'delete']],
            'external_connections' => ['label' => 'Conexiones Externas', 'permissions' => ['view', 'create', 'edit', 'delete']],

            // ============================================================
            // REPORTES (NUEVOS MÓDULOS)
            // ============================================================
            'cash_flow_report' => ['label' => 'Reporte Flujo de Caja', 'permissions' => ['view', 'export']],
            'transaction_report' => ['label' => 'Reporte Transacciones', 'permissions' => ['view', 'export']],
            'monthly_comparison_report' => ['label' => 'Reporte Comparativo Mensual', 'permissions' => ['view', 'export']],
            'yearly_summary_report' => ['label' => 'Reporte Resumen Anual', 'permissions' => ['view', 'export']],
            'cash_flow_projection_report' => ['label' => 'Reporte Proyección de Flujo', 'permissions' => ['view', 'export']],
        ];
    }

    /**
     * ✅ Sincronizar permisos (crea los que falten)
     */
    public static function syncPermissions(): void
    {
        $definitions = self::getPermissionDefinitions();

        foreach ($definitions as $group => $data) {
            foreach ($data['permissions'] as $action) {
                $name = $group . '_' . $action;
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
     * ✅ Obtener todos los permisos agrupados para UI
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
     * Obtener permisos para Select
     */
    public static function getPermissionsForSelect(): array
    {
        // ✅ Obtener TODOS los permisos de la BD directamente
        $permissions = \Spatie\Permission\Models\Permission::orderBy('name')->pluck('name', 'name')->toArray();

        $options = [];

        foreach ($permissions as $permissionName) {
            // ✅ Formatear el label
            $options[$permissionName] = self::formatPermissionLabel($permissionName);
        }

        return $options;
    }

    /**
     * ✅ Formatear el nombre del permiso para mostrar en el selector
     */
    protected static function formatPermissionLabel(string $permissionName): string
    {
        $definitions = self::getPermissionDefinitions();

        // Buscar el grupo y la acción
        foreach ($definitions as $group => $data) {
            foreach ($data['permissions'] as $action) {
                if ($permissionName === $group . '_' . $action) {
                    return $data['label'] . ' → ' . self::getActionLabel($action);
                }
            }
        }

        // Si no se encuentra, mostrar el nombre formateado
        return ucwords(str_replace('_', ' ', $permissionName));
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
}
