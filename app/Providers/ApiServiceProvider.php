<?php

namespace App\Providers;

use App\Services\Api\ApiClient;
use App\Services\Api\AuthService;
use App\Services\Context\CompanyContext;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrar ApiClient
        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                $app->make(CompanyContext::class)
            );
        });

        // Registrar AuthService
        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService(
                $app->make(ApiClient::class),
                $app->make(CompanyContext::class)
            );
        });

        // Registrar CompanyContext
        $this->app->singleton(CompanyContext::class, function ($app) {
            return new CompanyContext();
        });
    }

    public function boot(): void
    {
        // Configuración adicional si es necesaria
    }
}