<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Limpiar roles anteriores
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ✅ Crear roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        // ✅ Asignar roles a usuarios
        // Admin user - solo super_admin (sin user)
        $adminUser = User::where('email', 'admin@cashflow.com')->first();
        if ($adminUser) {
            // Limpiar roles existentes
            $adminUser->roles()->detach();
            // Asignar solo super_admin
            $adminUser->assignRole('super_admin');
            $this->command->info('✅ Admin asignado como super_admin');
        }

        // Demo user - solo user
        $demoUser = User::where('email', 'demo@cashflow.com')->first();
        if ($demoUser) {
            $demoUser->roles()->detach();
            $demoUser->assignRole('user');
            $this->command->info('✅ Demo asignado como user');
        }

        $this->command->info('✅ Roles asignados correctamente');
    }
}