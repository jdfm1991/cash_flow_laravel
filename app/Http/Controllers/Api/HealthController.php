<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * GET /api/health
     * Verificar estado completo de la API
     */
    public function check(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toDateTimeString(),
            'environment' => config('app.env'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ]);
    }

    /**
     * GET /api/version
     * Obtener versión de la API
     */
    public function version(): JsonResponse
    {
        return response()->json([
            'version' => '1.0.0',
            'name' => config('app.name'),
            'api_version' => 'v1',
            'release_date' => '2026-07-13',
        ]);
    }

    /**
     * GET /api/health/database
     * Verificar solo la base de datos
     */
    public function database(): JsonResponse
    {
        try {
            $pdo = DB::connection()->getPdo();
            
            return response()->json([
                'status' => 'connected',
                'message' => 'Conexión a base de datos exitosa',
                'database' => DB::connection()->getDatabaseName(),
                'driver' => DB::connection()->getDriverName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/health/cache
     * Verificar caché
     */
    public function cache(): JsonResponse
    {
        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            return response()->json([
                'status' => 'working',
                'message' => 'Caché funcionando correctamente',
                'driver' => config('cache.default'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/health/storage
     * Verificar almacenamiento
     */
    public function storage(): JsonResponse
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            
            return response()->json([
                'status' => 'accessible',
                'message' => 'Almacenamiento accesible',
                'disk' => 'public',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/health/ready
     * Verificar si la aplicación está lista para recibir tráfico
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $allOk = true;
        foreach ($checks as $check) {
            if (isset($check['status']) && $check['status'] === 'error') {
                $allOk = false;
                break;
            }
        }

        $status = $allOk ? 'ready' : 'not_ready';
        $httpCode = $allOk ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toDateTimeString(),
            'checks' => $checks,
        ], $httpCode);
    }

    /**
     * GET /api/health/live
     * Verificar si la aplicación está viva (liveness probe)
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Verificar conexión a base de datos
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'connected',
                'message' => 'Conexión exitosa',
                'database' => DB::connection()->getDatabaseName(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verificar caché
     */
    protected function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'ok', 10);
            Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'status' => 'working',
                'message' => 'Caché funcionando correctamente',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verificar almacenamiento
     */
    protected function checkStorage(): array
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            $disk->exists('/'); // Solo verificar acceso

            return [
                'status' => 'accessible',
                'message' => 'Almacenamiento accesible',
                'disk' => 'public',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}