<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Limpiar caché
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ✅ Crear permisos
        $permissions = [
            // Administración
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
            'view_companies', 'create_companies', 'edit_companies', 'delete_companies',

            // Catálogos
            'view_currencies', 'create_currencies', 'edit_currencies', 'delete_currencies',
            'view_banks', 'create_banks', 'edit_banks', 'delete_banks',
            'view_plans', 'create_plans', 'edit_plans', 'delete_plans',
            'view_categories', 'create_categories', 'edit_categories', 'delete_categories',
            'view_accounts', 'create_accounts', 'edit_accounts', 'delete_accounts',

            // Finanzas
            'view_bank_accounts', 'create_bank_accounts', 'edit_bank_accounts', 'delete_bank_accounts',
            'view_transactions', 'create_transactions', 'edit_transactions', 'delete_transactions',
            'view_exchange_rates', 'create_exchange_rates', 'edit_exchange_rates', 'delete_exchange_rates',

            // Reportes
            'view_reports', 'export_reports',

            // Importaciones
            'import_data', 'view_imports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // ✅ Crear roles
        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'is_system' => true,
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'is_system' => true,
        ]);

        $accountant = Role::firstOrCreate([
            'name' => 'accountant',
            'guard_name' => 'web',
            'is_system' => true,
        ]);

        $user = Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web',
            'is_system' => true,
        ]);

        $viewer = Role::firstOrCreate([
            'name' => 'viewer',
            'guard_name' => 'web',
            'is_system' => true,
        ]);

        // ✅ Asignar permisos a roles
        $superAdmin->syncPermissions(Permission::all());

        $admin->syncPermissions([
            'view_users', 'create_users', 'edit_users',
            'view_companies', 'view_currencies', 'view_banks', 'view_plans',
            'view_categories', 'view_accounts',
            'view_bank_accounts', 'create_bank_accounts', 'edit_bank_accounts',
            'view_transactions', 'create_transactions', 'edit_transactions',
            'view_exchange_rates',
            'view_reports', 'export_reports',
            'import_data', 'view_imports',
        ]);

        $accountant->syncPermissions([
            'view_transactions', 'create_transactions', 'edit_transactions',
            'view_bank_accounts', 'view_reports', 'export_reports',
        ]);

        $user->syncPermissions([
            'view_transactions', 'create_transactions',
            'view_bank_accounts', 'view_reports',
        ]);

        $viewer->syncPermissions([
            'view_transactions', 'view_reports',
        ]);

        // ✅ Asignar super_admin al primer usuario
        $user = User::first();
        if ($user) {
            $user->assignRole('super_admin');
            $this->command->info("✅ Rol super_admin asignado a: {$user->email}");
        }

        $this->command->info('✅ Roles y permisos creados correctamente.');
    }
}