<?php

namespace App\Console;

use App\Console\Commands\CheckUserModel;
use App\Console\Commands\CheckUserPermissions;
use App\Console\Commands\CheckUserRoles;
use App\Console\Commands\CleanTempFiles;
use App\Console\Commands\SyncPermissions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        CheckUserModel::class,      // ✅ Registrar el comando
        CheckUserRoles::class,
        CheckUserPermissions::class,
        SyncPermissions::class,
        CleanTempFiles::class
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Limpiar archivos temporales cada hora (eliminar archivos con más de 2 horas)
        $schedule->command('clean:temp-files --hours=2')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/clean-temp-files.log'));

        // También puedes ejecutarlo diariamente para limpieza más agresiva
        $schedule->command('clean:temp-files --hours=24')
            ->daily()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/clean-temp-files.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
