<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckUserPermissions extends Command
{
    protected $signature = 'user:permissions {email}';
    protected $description = 'Verificar permisos de un usuario';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Usuario no encontrado: {$email}");
            return Command::FAILURE;
        }

        $this->info("=== USUARIO: {$user->name} ({$user->email}) ===");
        $this->line("");

        // Roles
        $this->info("📋 Roles asignados:");
        if ($user->roles->isEmpty()) {
            $this->warn("  ❌ Sin roles");
        } else {
            foreach ($user->roles as $role) {
                $this->line("  ✅ {$role->name}" . ($role->is_system ? ' (Sistema)' : ''));
            }
        }
        $this->line("");

        // Permisos directos
        $this->info("📋 Permisos directos:");
        $directPermissions = $user->getDirectPermissions();
        if ($directPermissions->isEmpty()) {
            $this->warn("  ❌ Sin permisos directos");
        } else {
            foreach ($directPermissions as $permission) {
                $this->line("  ✅ {$permission->name}");
            }
        }
        $this->line("");

        // Permisos totales (vía roles)
        $this->info("📋 Permisos totales (heredados):");
        $allPermissions = $user->getAllPermissions();
        if ($allPermissions->isEmpty()) {
            $this->warn("  ❌ Sin permisos");
        } else {
            foreach ($allPermissions as $permission) {
                $this->line("  ✅ {$permission->name}");
            }
        }
        $this->line("");

        // Verificar permisos específicos
        $this->info("📋 Verificación de permisos específicos:");
        $tests = [
            'users_view',
            'users_create',
            'users_edit',
            'users_delete',
            'roles_view',
            'transactions_view',
            'reports_view',
            'reports_export',
        ];

        foreach ($tests as $permission) {
            $has = $user->can($permission);
            $icon = $has ? '✅' : '❌';
            $this->line("  {$icon} {$permission}");
        }

        return Command::SUCCESS;
    }
}