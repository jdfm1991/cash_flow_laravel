<?php

namespace Database\Seeders;

use App\Services\PermissionService;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Sincronizar todos los permisos
        PermissionService::syncPermissions();
        
        $this->command->info('✅ Permisos sincronizados correctamente.');
        
        // Mostrar los permisos creados
        $permissions = \Spatie\Permission\Models\Permission::pluck('name')->toArray();
        $this->command->info('📋 Permisos disponibles:');
        foreach ($permissions as $permission) {
            $this->command->line("  - {$permission}");
        }
    }
}