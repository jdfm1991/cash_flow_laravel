<?php

namespace App\Jobs;

use App\Models\ImportSession;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessExcelImportJob implements ShouldQueue
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
        protected string $sessionId
    ) {}

    /**
     * Ejecutar el job.
     */
    public function handle(ImportService $importService): void
    {
        try {
            Log::info("Iniciando procesamiento de importación Excel", [
                'session_id' => $this->sessionId,
            ]);

            // 1. Obtener la sesión
            $session = ImportSession::find($this->sessionId);
            
            if (!$session) {
                Log::error("Sesión de importación no encontrada", [
                    'session_id' => $this->sessionId,
                ]);
                return;
            }

            // 2. Marcar como procesando
            $session->start();

            // 3. Procesar la importación
            $result = $importService->processExcelImport($session);

            // 4. Marcar como completada
            $result->complete();

            Log::info("Importación Excel completada exitosamente", [
                'session_id' => $this->sessionId,
                'processed' => $result->processed_rows,
                'duplicated' => $result->duplicated_rows,
                'errors' => $result->error_rows,
            ]);

        } catch (Throwable $e) {
            // 5. Marcar como fallida
            $this->markAsFailed($e);

            Log::error("Error en ProcessExcelImportJob", [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-lanzar para que el sistema maneje el reintento
            throw $e;
        }
    }

    /**
     * Marcar la sesión como fallida.
     */
    protected function markAsFailed(Throwable $e): void
    {
        try {
            $session = ImportSession::find($this->sessionId);
            if ($session) {
                $session->fail($e->getMessage());
            }
        } catch (\Exception $inner) {
            Log::error("Error al marcar sesión como fallida", [
                'session_id' => $this->sessionId,
                'error' => $inner->getMessage(),
            ]);
        }
    }

    /**
     * Manejar fallo del job.
     */
    public function failed(Throwable $e): void
    {
        Log::error("ProcessExcelImportJob falló después de múltiples intentos", [
            'session_id' => $this->sessionId,
            'error' => $e->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->markAsFailed($e);
    }
}