<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class GateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ Super_admin puede hacer todo
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        // Definir permisos para otros roles
        Gate::define('manage-banks', function (User $user) {
            return $user->hasRole('super_admin');
        });

        Gate::define('manage-currencies', function (User $user) {
            return $user->hasRole('super_admin');
        });

        Gate::define('manage-accounts', function (User $user) {
            return $user->hasRole('super_admin') || $user->hasRole('admin');
        });
    }
}