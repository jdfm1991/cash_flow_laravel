<?php

namespace Database\Seeders;

use App\Services\PermissionService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 Sincronizando permisos...');

        // ✅ 1. Sincronizar todos los permisos (crea los que falten)
        PermissionService::syncPermissions();

        $this->command->info('✅ Permisos sincronizados.');

        // ✅ 2. Crear roles base si no existen
        $this->createBaseRoles();

        // ✅ 3. Asignar permisos a roles según su propósito
        $this->assignPermissionsToRoles();

        // ✅ 4. Asignar super_admin al primer usuario si no tiene rol
        $this->assignSuperAdminToFirstUser();

        // ✅ 5. Mostrar resumen
        $this->showSummary();
    }

    /**
     * Crear roles base
     */
    protected function createBaseRoles(): void
    {
        $roles = [
            ['name' => 'super_admin', 'description' => 'Acceso total al sistema', 'is_system' => true],
            ['name' => 'admin', 'description' => 'Gestión completa de la empresa', 'is_system' => true],
            ['name' => 'accountant', 'description' => 'Acceso a módulos contables y financieros', 'is_system' => true],
            ['name' => 'user', 'description' => 'Acceso básico para operaciones diarias', 'is_system' => true],
            ['name' => 'viewer', 'description' => 'Solo visualización de datos', 'is_system' => true],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => 'web'],
                [
                    'description' => $roleData['description'],
                    'is_system' => $roleData['is_system'],
                ]
            );
        }

        $this->command->info('✅ Roles base creados/verificados.');
    }

    /**
     * Asignar permisos a roles según su propósito
     */
    protected function assignPermissionsToRoles(): void
    {
        $allPermissions = Permission::where('guard_name', 'web')->get();

        // ✅ Super Admin: TODOS los permisos
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions($allPermissions);
            $this->command->info("  ✅ super_admin: {$allPermissions->count()} permisos");
        }

        // ✅ Admin: Todos excepto gestión de roles (solo super_admin puede)
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $adminPermissions = $allPermissions->filter(function ($p) {
                return !str_starts_with($p->name, 'roles_');
            });
            $admin->syncPermissions($adminPermissions);
            $this->command->info("  ✅ admin: {$adminPermissions->count()} permisos");
        }

        // ✅ Accountant: Transacciones, reportes y catálogos
        $accountant = Role::where('name', 'accountant')->first();
        if ($accountant) {
            $accountantPermissions = $allPermissions->filter(function ($p) {
                return str_starts_with($p->name, 'transactions_')
                    || str_starts_with($p->name, 'reports_')
                    || str_starts_with($p->name, '_report_')
                    || str_starts_with($p->name, 'categories_')
                    || str_starts_with($p->name, 'accounts_')
                    || str_starts_with($p->name, 'bank_accounts_view')
                    || str_starts_with($p->name, 'exchange_rates_view');
            });
            $accountant->syncPermissions($accountantPermissions);
            $this->command->info("  ✅ accountant: {$accountantPermissions->count()} permisos");
        }

        // ✅ User: Permisos básicos
        $user = Role::where('name', 'user')->first();
        if ($user) {
            $userPermissions = $allPermissions->filter(function ($p) {
                return in_array($p->name, [
                    'transactions_view',
                    'transactions_create',
                    'bank_accounts_view',
                    'cash_flow_report_view',
                    'transaction_report_view',
                ]);
            });
            $user->syncPermissions($userPermissions);
            $this->command->info("  ✅ user: {$userPermissions->count()} permisos");
        }

        // ✅ Viewer: Solo visualización
        $viewer = Role::where('name', 'viewer')->first();
        if ($viewer) {
            $viewerPermissions = $allPermissions->filter(function ($p) {
                return str_ends_with($p->name, '_view');
            });
            $viewer->syncPermissions($viewerPermissions);
            $this->command->info("  ✅ viewer: {$viewerPermissions->count()} permisos");
        }
    }

    /**
     * Asignar super_admin al primer usuario si no tiene rol
     */
    protected function assignSuperAdminToFirstUser(): void
    {
        $firstUser = User::whereDoesntHave('roles')->first();

        if ($firstUser) {
            $firstUser->assignRole('super_admin');
            $this->command->info("✅ Rol super_admin asignado a: {$firstUser->email}");
        } else {
            $superAdminUser = User::where('email', 'admin@cashflow.com')->first();
            if ($superAdminUser && !$superAdminUser->hasRole('super_admin')) {
                $superAdminUser->assignRole('super_admin');
                $this->command->info("✅ Rol super_admin asignado a: {$superAdminUser->email}");
            }
        }
    }

    /**
     * Mostrar resumen final
     */
    protected function showSummary(): void
    {
        $this->command->newLine();
        $this->command->info('📊 RESUMEN:');
        $this->command->table(
            ['Rol', 'Permisos', 'Usuarios'],
            Role::withCount(['permissions', 'users'])->get()->map(function ($role) {
                return [
                    $role->name,
                    $role->permissions_count,
                    $role->users_count,
                ];
            })->toArray()
        );

        $this->command->newLine();
        $this->command->info('📋 Total de permisos: ' . Permission::count());
        $this->command->info('📋 Total de roles: ' . Role::count());
    }
}