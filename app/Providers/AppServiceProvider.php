<?php

namespace App\Providers;

use App\Services\TransactionService;
use App\Services\CurrencyService;
use App\Services\BalanceService;
use App\Services\BankStatementParserService;
use App\Services\ContabilidadIntegrationService;
use App\Services\ExternalConnectionService;
use App\Services\TransactionImportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use App\Observers\AuditObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use App\Listeners\LogAuthenticationEvents;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrar servicios como singletons
        $this->app->singleton(CurrencyService::class, function ($app) {
            return new CurrencyService();
        });

        $this->app->singleton(BalanceService::class, function ($app) {
            return new BalanceService();
        });

        $this->app->singleton(ContabilidadIntegrationService::class, function ($app) {
            return new ContabilidadIntegrationService();
        });

        $this->app->singleton(ExternalConnectionService::class, function ($app) {
            return new ExternalConnectionService();
        });

        $this->app->singleton(BankStatementParserService::class, function ($app) {
            return new BankStatementParserService();
        });

        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService(
                currencyService: $app->make(CurrencyService::class),
                balanceService: $app->make(BalanceService::class),
                contabilidadService: $app->make(ContabilidadIntegrationService::class),
            );
        });

        $this->app->singleton(TransactionImportService::class, function ($app) {
            return new TransactionImportService(
                $app->make(TransactionService::class),
                $app->make(CurrencyService::class)
            );
        });
    }

    public function boot(): void
    {
        // Crear directorios temporales si no existen
        $this->ensureTempDirectoriesExist();
        // ✅ Registrar listeners de autenticación
        Event::listen(Login::class, [LogAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthenticationEvents::class, 'handleLogout']);
        Event::listen(Failed::class, [LogAuthenticationEvents::class, 'handleFailed']);

        // ✅ Registrar eventos Eloquent globales
        $this->registerAuditEvents();
    }

    /**
     * ✅ Registrar eventos Eloquent globales para auditoría
     */
    protected function registerAuditEvents(): void
    {
        $observer = app(AuditObserver::class);

        // ✅ Escuchar eventos globales de Eloquent
        Event::listen('eloquent.created: *', function ($eventName, $data) use ($observer) {
            $model = $data[0] ?? null;
            if ($model instanceof Model) {
                $observer->created($model);
            }
        });

        Event::listen('eloquent.updated: *', function ($eventName, $data) use ($observer) {
            $model = $data[0] ?? null;
            if ($model instanceof Model) {
                $observer->updated($model);
            }
        });

        Event::listen('eloquent.deleted: *', function ($eventName, $data) use ($observer) {
            $model = $data[0] ?? null;
            if ($model instanceof Model) {
                $observer->deleted($model);
            }
        });

        Event::listen('eloquent.restored: *', function ($eventName, $data) use ($observer) {
            $model = $data[0] ?? null;
            if ($model instanceof Model) {
                $observer->restored($model);
            }
        });
    }

    /**
     * Asegurar que los directorios temporales existan
     */
    protected function ensureTempDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/temp'),
            storage_path('app/temp/pdf'),
            storage_path('app/temp/charts'),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                try {
                    mkdir($directory, 0777, true);
                    Log::info("Directorio temporal creado: {$directory}");
                } catch (\Exception $e) {
                    Log::error("Error creando directorio temporal: {$directory}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
