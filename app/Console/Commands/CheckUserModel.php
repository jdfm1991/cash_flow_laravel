<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckUserModel extends Command
{
    protected $signature = 'user:check {email?}';
    protected $description = 'Verificar el modelo User y sus traits';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Email del usuario');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Usuario no encontrado con email: {$email}");
            return Command::FAILURE;
        }

        $this->info("=== VERIFICANDO MODELO USER ===");
        $this->line("");
        $this->info("Usuario: {$user->name} ({$user->email})");
        $this->info("ID: {$user->id}");
        $this->line("");

        // Verificar traits
        $this->info("=== TRAITS ===");
        $traits = class_uses_recursive($user);
        foreach ($traits as $trait) {
            $this->line("  ✅ " . class_basename($trait));
        }
        $this->line("");

        // Verificar métodos
        $this->info("=== MÉTODOS DISPONIBLES ===");
        $methods = get_class_methods($user);
        $methodChecks = [
            'hasRole' => 'HasRoles',
            'can' => 'HasRoles',
            'assignRole' => 'HasRoles',
            'syncRoles' => 'HasRoles',
            'permissions' => 'HasRoles',
        ];

        foreach ($methodChecks as $method => $traitName) {
            if (method_exists($user, $method)) {
                $this->line("  ✅ {$method} (disponible)");
            } else {
                $this->line("  ❌ {$method} (NO disponible - falta {$traitName})");
            }
        }
        $this->line("");

        // Verificar roles
        $this->info("=== ROLES ===");
        if (method_exists($user, 'hasRole')) {
            $roles = $user->roles;
            if ($roles->isEmpty()) {
                $this->warn("  ❌ Sin roles asignados");
            } else {
                foreach ($roles as $role) {
                    $this->line("  ✅ {$role->name}");
                }
            }
        } else {
            $this->error("  ❌ No se pueden verificar roles (hasRole no disponible)");
        }
        $this->line("");

        // Verificar permisos
        $this->info("=== PERMISOS ===");
        if (method_exists($user, 'can')) {
            // Verificar permisos específicos
            $permissions = ['users_view', 'roles_view', 'users_create', 'roles_create'];
            foreach ($permissions as $permission) {
                $hasPermission = $user->can($permission);
                $icon = $hasPermission ? '✅' : '❌';
                $this->line("  {$icon} {$permission}");
            }
        } else {
            $this->error("  ❌ No se pueden verificar permisos (can no disponible)");
        }
        $this->line("");

        $this->info("=== RECOMENDACIONES ===");
        if (!in_array('HasRoles', array_map('class_basename', $traits))) {
            $this->error("  ❌ El modelo User NO tiene el trait HasRoles");
            $this->line("  Agrega: use Spatie\\Permission\\Traits\\HasRoles;");
            $this->line("  Y: use HasRoles; en la clase User");
        } else {
            $this->info("  ✅ El modelo User tiene HasRoles correctamente");
        }

        return Command::SUCCESS;
    }
}