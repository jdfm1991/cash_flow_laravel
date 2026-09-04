<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckUserRoles extends Command
{
    protected $signature = 'user:roles {email?}';
    protected $description = 'Verificar los roles de un usuario';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Email del usuario');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Usuario no encontrado con email: {$email}");
            return Command::FAILURE;
        }

        $this->info("Usuario: {$user->name} ({$user->email})");
        $this->info("ID: {$user->id}");
        $this->info("Roles:");

        if ($user->roles->isEmpty()) {
            $this->warn("  ❌ Sin roles asignados");
        } else {
            foreach ($user->roles as $role) {
                $this->line("  ✅ {$role->name}");
            }
        }

        $this->info("\nPermisos:");
        $permissions = $user->getAllPermissions();
        if ($permissions->isEmpty()) {
            $this->warn("  ❌ Sin permisos directos");
        } else {
            foreach ($permissions as $permission) {
                $this->line("  ✅ {$permission->name}");
            }
        }

        return Command::SUCCESS;
    }
}