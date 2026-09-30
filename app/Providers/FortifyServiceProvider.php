<?php

namespace App\Providers;

use App\Services\Api\AuthService;
use App\Services\Context\CompanyContext;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Listeners\LogAuthenticationEvents;

class FortifyServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            [LogAuthenticationEvents::class, 'handleLogin'],
        ],
        Logout::class => [
            [LogAuthenticationEvents::class, 'handleLogout'],
        ],
        Failed::class => [
            [LogAuthenticationEvents::class, 'handleFailed'],
        ],
    ];

    public function register(): void
    {
        $this->app->singleton(AuthService::class);
        $this->app->singleton(CompanyContext::class);
    }

    public function boot(): void
    {
        //parent::boot();
    }
}
