<?php

namespace App\Jobs;

use App\Models\MigrationLog;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDatabaseMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos del job.
     */
    public int $tries = 3;

    /**
     * Tiempo de espera entre reintentos (segundos).
     */
    public int $backoff = 30;

    /**
     * Timeout del job (segundos).
     */
    public int $timeout = 3600; // 1 hora

    public function __construct(
        protected int $logId
    ) {}

    /**
     * Ejecutar el job.
     */
    public function handle(ImportService $importService): void
    {
        try {
            Log::info("Iniciando procesamiento de migración desde base de datos", [
                'log_id' => $this->logId,
            ]);

            // 1. Obtener el log de migración
            $log = MigrationLog::find($this->logId);
            
            if (!$log) {
                Log::error("Log de migración no encontrado", [
                    'log_id' => $this->logId,
                ]);
                return;
            }

            // 2. Procesar la migración
            $result = $importService->processMigration($log);

            Log::info("Migración desde base de datos completada exitosamente", [
                'log_id' => $this->logId,
                'imported' => $result->imported_records,
                'duplicated' => $result->duplicated_records,
                'failed' => $result->failed_records,
            ]);

        } catch (Throwable $e) {
            // 3. Marcar como fallida
            $this->markAsFailed($e);

            Log::error("Error en ProcessDatabaseMigrationJob", [
                'log_id' => $this->logId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Marcar el log como fallido.
     */
    protected function markAsFailed(Throwable $e): void
    {
        try {
            $log = MigrationLog::find($this->logId);
            if ($log) {
                $log->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_log' => [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ],
                ]);
            }
        } catch (\Exception $inner) {
            Log::error("Error al marcar log como fallido", [
                'log_id' => $this->logId,
                'error' => $inner->getMessage(),
            ]);
        }
    }

    /**
     * Manejar fallo del job.
     */
    public function failed(Throwable $e): void
    {
        Log::error("ProcessDatabaseMigrationJob falló después de múltiples intentos", [
            'log_id' => $this->logId,
            'error' => $e->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->markAsFailed($e);
    }
}