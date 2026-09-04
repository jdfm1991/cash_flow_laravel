<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Crear permisos
        $permissions = [
            // Permisos para Catálogos
            'view_currencies',
            'create_currencies',
            'edit_currencies',
            'delete_currencies',

            'view_banks',
            'create_banks',
            'edit_banks',
            'delete_banks',

            'view_categories',
            'create_categories',
            'edit_categories',
            'delete_categories',

            'view_accounts',
            'create_accounts',
            'edit_accounts',
            'delete_accounts',

            // Permisos para Finanzas
            'view_transactions',
            'create_transactions',
            'edit_transactions',
            'delete_transactions',

            'view_bank_accounts',
            'create_bank_accounts',
            'edit_bank_accounts',
            'delete_bank_accounts',

            'view_exchange_rates',
            'create_exchange_rates',
            'edit_exchange_rates',
            'delete_exchange_rates',

            // Permisos para Administración
            'view_companies',
            'create_companies',
            'edit_companies',
            'delete_companies',

            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',

            // Permisos para Reportes
            'view_reports',
            'export_reports',

            // Permisos para Importación
            'import_data',
            'view_imports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // 3. Crear roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'sanctum']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'sanctum']);
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'sanctum']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'sanctum']);

        // 4. Asignar todos los permisos a super_admin
        $superAdmin->givePermissionTo(Permission::all());

        // 5. Asignar permisos a admin
        $admin->givePermissionTo([
            'view_currencies', 'view_banks', 'view_categories', 'view_accounts',
            'view_transactions', 'create_transactions', 'edit_transactions',
            'view_bank_accounts', 'create_bank_accounts', 'edit_bank_accounts',
            'view_companies', 'view_users',
            'view_reports', 'export_reports',
            'import_data', 'view_imports',
        ]);

        // 6. Asignar permisos a accountant
        $accountant->givePermissionTo([
            'view_transactions', 'create_transactions', 'edit_transactions',
            'view_bank_accounts', 'view_reports', 'export_reports',
        ]);

        // 7. Asignar permisos a viewer
        $viewer->givePermissionTo([
            'view_transactions', 'view_reports',
        ]);
    }
}

