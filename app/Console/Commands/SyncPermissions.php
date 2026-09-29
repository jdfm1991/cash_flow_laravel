<?php

namespace App\Console\Commands;

use App\Services\PermissionService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync 
                            {--show : Mostrar todos los permisos}';
    protected $description = 'Sincronizar todos los permisos del sistema';

    public function handle(): int
    {
        $this->info('🔄 Sincronizando permisos...');
        
        PermissionService::syncPermissions();
        
        $this->info('✅ Permisos sincronizados correctamente.');
        
        if ($this->option('show')) {
            $this->newLine();
            $this->info('📋 Permisos disponibles:');
            
            $grouped = PermissionService::getGroupedPermissionsForUI();
            foreach ($grouped as $group => $data) {
                $this->line("");
                $this->line("📂 {$data['label']}:");
                foreach ($data['permissions'] as $permission) {
                    $exists = Permission::where('name', $permission['name'])->exists();
                    $icon = $exists ? '✅' : '❌';
                    $this->line("  {$icon} {$permission['name']} - {$permission['label']}");
                }
            }
        }
        
        return Command::SUCCESS;
    }
}