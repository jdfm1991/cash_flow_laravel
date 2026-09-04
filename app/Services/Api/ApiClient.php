<?php

namespace App\Services\Api;

use App\Enums\HttpMethod;
use App\Exceptions\ApiException;
use App\Services\Context\CompanyContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;

class ApiClient
{
    /**
     * URL base de la API
     */
    private string $baseUrl;

    /**
     * Token de autenticación
     */
    private ?string $token = null;

    /**
     * ID de la empresa activa
     */
    private ?int $companyId = null;

    /**
     * Timeout en segundos
     */
    private int $timeout = 30;

    /**
     * Número máximo de reintentos
     */
    private int $maxRetries = 3;

    public function __construct(
        private CompanyContext $companyContext
    ) {
        // ✅ Usar config('app.api_url')
        $this->baseUrl = rtrim(config('app.api_url', 'http://127.0.0.1:8000/api'), '/');

        $this->token = Session::get('api_token');
        $this->companyId = $this->companyContext->getCurrentCompanyId();
    }

    /**
     * Establecer token de autenticación
     */
    public function setToken(string $token): self
    {
        $this->token = $token;
        Session::put('api_token', $token);
        return $this;
    }

    /**
     * Limpiar token de autenticación
     */
    public function clearToken(): self
    {
        $this->token = null;
        Session::forget('api_token');
        return $this;
    }

    /**
     * Establecer ID de empresa
     */
    public function setCompanyId(int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    /**
     * Establecer timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Establecer número máximo de reintentos
     */
    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = $retries;
        return $this;
    }

    /**
     * Realizar petición GET
     */
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request(HttpMethod::GET, $endpoint, ['query' => $params]);
    }

    /**
     * Realizar petición POST
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request(HttpMethod::POST, $endpoint, ['json' => $data]);
    }

    /**
     * Realizar petición PUT
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request(HttpMethod::PUT, $endpoint, ['json' => $data]);
    }

    /**
     * Realizar petición PATCH
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request(HttpMethod::PATCH, $endpoint, ['json' => $data]);
    }

    /**
     * Realizar petición DELETE
     */
    public function delete(string $endpoint, array $data = []): array
    {
        return $this->request(HttpMethod::DELETE, $endpoint, ['json' => $data]);
    }

    /**
     * Petición genérica con reintentos
     */
    private function request(HttpMethod $method, string $endpoint, array $options = []): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                return $this->doRequest($method, $endpoint, $options);
            } catch (ApiException $e) {
                $lastException = $e;

                // Si es error 401 (no autenticado) y no es un endpoint de login
                if ($e->getCode() === 401 && !$this->isAuthEndpoint($endpoint)) {
                    // Intentar refrescar token
                    if ($this->refreshToken()) {
                        $attempts++;
                        continue;
                    }
                }

                // Si es error 403 (prohibido), no reintentar
                if ($e->getCode() === 403) {
                    throw $e;
                }

                // Si es error 422 (validación), no reintentar
                if ($e->getCode() === 422) {
                    throw $e;
                }

                // Para otros errores, reintentar con backoff
                $attempts++;
                if ($attempts < $this->maxRetries) {
                    usleep(100000 * $attempts); // 100ms, 200ms, 300ms
                }
            }
        }

        throw $lastException ?? new ApiException(
            'No se pudo completar la petición después de varios intentos',
            [],
            500
        );
    }

    /**
     * Ejecutar una petición HTTP
     */
    private function doRequest(HttpMethod $method, string $endpoint, array $options = []): array
    {
        // Construir la URL completa
        $url = $this->buildUrl($endpoint);

        // Construir headers
        $headers = $this->buildHeaders();

        // Crear la petición
        $request = Http::withHeaders($headers)
            ->timeout($this->timeout);

        // Agregar opciones específicas
        if (isset($options['query'])) {
            $request = $request->withQueryParameters($options['query']);
        }

        // Ejecutar la petición según el método
        $response = match ($method) {
            HttpMethod::GET => $request->get($url),
            HttpMethod::POST => $request->post($url, $options['json'] ?? []),
            HttpMethod::PUT => $request->put($url, $options['json'] ?? []),
            HttpMethod::PATCH => $request->patch($url, $options['json'] ?? []),
            HttpMethod::DELETE => $request->delete($url, $options['json'] ?? []),
        };

        // Log de peticiones en desarrollo
        if (config('app.debug')) {
            $this->logRequest($method, $url, $options, $response);
        }

        // Procesar la respuesta
        return $this->processResponse($response, $endpoint);
    }

    /**
     * Construir URL completa
     */
    private function buildUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');
        return "{$this->baseUrl}/{$endpoint}";
    }

    /**
     * Construir headers de la petición
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        // Agregar token de autenticación
        if ($this->token) {
            $headers['Authorization'] = "Bearer {$this->token}";
        }

        // Agregar contexto de empresa
        if ($this->companyId) {
            $headers['X-Company-Id'] = (string) $this->companyId;
        }

        return $headers;
    }

    /**
     * Procesar la respuesta de la API
     */
    private function processResponse(Response $response, string $endpoint): array
    {
        // Si la respuesta no es exitosa
        if (!$response->successful()) {
            $this->handleErrorResponse($response, $endpoint);
        }

        // Obtener los datos
        $data = $response->json();

        // ✅ LOG PARA DEPURACIÓN
        \Illuminate\Support\Facades\Log::debug('API Response', [
            'endpoint' => $endpoint,
            'data' => $data,
        ]);

        // Verificar si la API usa el formato { success: true, data: ... }
        if (isset($data['success']) && $data['success'] === false) {
            throw new ApiException(
                $data['message'] ?? 'Error en la API',
                $data['errors'] ?? [],
                $response->status(),
                $data['data'] ?? null
            );
        }

        // Si la API envuelve en 'data', extraerlo
        if (isset($data['data']) && isset($data['success'])) {
            return $data['data'];
        }

        // Si solo hay 'data' sin 'success', devolverlo
        if (isset($data['data']) && !isset($data['success'])) {
            return $data['data'];
        }

        // Si es una respuesta simple (como un mensaje)
        if (isset($data['message']) && !isset($data['data'])) {
            return $data;
        }

        // En cualquier otro caso, devolver todo
        return $data;
    }

    /**
     * Manejar respuestas de error
     */
    private function handleErrorResponse(Response $response, string $endpoint): void
    {
        $status = $response->status();
        $data = $response->json();
        $message = $data['message'] ?? $this->getDefaultErrorMessage($status);
        $errors = $data['errors'] ?? [];

        // Log de errores en desarrollo
        if (config('app.debug')) {
            Log::error('API Error', [
                'status' => $status,
                'endpoint' => $endpoint,
                'message' => $message,
                'errors' => $errors,
                'response' => $response->body(),
            ]);
        }

        throw new ApiException($message, $errors, $status);
    }

    /**
     * Obtener mensaje de error por defecto según código HTTP
     */
    private function getDefaultErrorMessage(int $status): string
    {
        return match ($status) {
            400 => 'Solicitud incorrecta',
            401 => 'No autenticado. Por favor, inicia sesión nuevamente.',
            403 => 'No tienes permisos para realizar esta acción',
            404 => 'Recurso no encontrado',
            422 => 'Error de validación',
            429 => 'Demasiadas peticiones. Intenta nuevamente más tarde.',
            500 => 'Error interno del servidor',
            503 => 'Servicio no disponible',
            default => "Error en la API (Código: {$status})",
        };
    }

    /**
     * Refrescar token automáticamente
     */
    private function refreshToken(): bool
    {
        try {
            // Crear petición sin token para el refresh
            $request = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $response = $request->post("{$this->baseUrl}/auth/refresh");

            if ($response->successful()) {
                $data = $response->json();
                $newToken = $data['access_token'] ?? $data['data']['access_token'] ?? null;

                if ($newToken) {
                    $this->setToken($newToken);
                    return true;
                }
            }

            // Si falla el refresh, limpiar token y forzar logout
            $this->clearToken();
            Session::forget('api_user');

            return false;
        } catch (\Exception $e) {
            Log::warning('Token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            $this->clearToken();
            Session::forget('api_user');

            return false;
        }
    }

    /**
     * Verificar si el endpoint es de autenticación
     */
    private function isAuthEndpoint(string $endpoint): bool
    {
        return str_starts_with($endpoint, 'auth/')
            || $endpoint === 'auth'
            || $endpoint === 'login';
    }

    /**
     * Log de peticiones (solo en desarrollo)
     */
    private function logRequest(HttpMethod $method, string $url, array $options, Response $response): void
    {
        Log::debug('API Request', [
            'method' => $method->value,
            'url' => $url,
            'options' => $options,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);
    }

    /**
     * Verificar si el cliente está autenticado
     */
    public function isAuthenticated(): bool
    {
        return $this->token !== null;
    }

    /**
     * Obtener el token actual
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * Obtener la empresa activa
     */
    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    /**
     * Verificar conexión con la API
     */
    public function checkConnection(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/public/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtener versión de la API
     */
    public function getVersion(): ?string
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/public/version");
            return $response->json('version') ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
