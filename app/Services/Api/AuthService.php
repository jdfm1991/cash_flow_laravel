<?php

namespace App\Services\Api;

use App\Exceptions\ApiException;
use App\Services\Context\CompanyContext;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthService
{
    /**
     * @var ApiClient Cliente HTTP para peticiones a la API
     */
    private ApiClient $apiClient;

    /**
     * @var CompanyContext Contexto de empresa activa
     */
    private CompanyContext $companyContext;

    /**
     * @var string|null Token de autenticación actual
     */
    private ?string $currentToken = null;

    /**
     * @var array|null Datos del usuario autenticado
     */
    private ?array $currentUser = null;

    public function __construct(
        ApiClient $apiClient,
        CompanyContext $companyContext
    ) {
        $this->apiClient = $apiClient;
        $this->companyContext = $companyContext;

        // Cargar datos de sesión si existen
        $this->loadFromSession();
    }

    /**
     * Iniciar sesión con credenciales
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        Log::info('🔵 AuthService: Iniciando login', ['email' => $email]);

        try {
            Log::info('🔵 AuthService: Llamando a ApiClient->post()');

            $response = $this->apiClient->post('/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

            Log::info('🔵 AuthService: Respuesta de ApiClient recibida', [
                'has_token' => isset($response['token']),
                'has_user' => isset($response['user']),
            ]);

            $token = $response['token'] ?? null;
            $user = $response['user'] ?? null;

            if (!$token || !$user) {
                Log::error('🔴 AuthService: Token o usuario faltante');
                throw new ApiException('Respuesta de login inválida', [], 500);
            }

            // Normalizar usuario
            $user['companies'] = $response['companies'] ?? [];
            $user['current_company'] = $response['current_company'] ?? null;
            $user['roles'] = $response['roles'] ?? [];
            $user['permissions'] = $response['permissions'] ?? [];
            $user['current_company_id'] = $user['current_company']['id'] ?? null;

            Log::info('🔵 AuthService: Guardando token en ApiClient');
            $this->apiClient->setToken($token);
            $this->currentToken = $token;

            Log::info('🔵 AuthService: Guardando usuario en sesión');
            $this->saveUserToSession($user);

            Log::info('🔵 AuthService: Estableciendo empresa por defecto');
            $this->setDefaultCompany($user);

            Log::info('🔵 AuthService: Autenticando en Laravel');
            $this->authenticateLaravelUser($user);

            Log::info('✅ AuthService: Login completado exitosamente');

            return [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'companies' => $response['companies'] ?? [],
                'current_company' => $response['current_company'] ?? null,
            ];
        } catch (ApiException $e) {
            Log::error('🔴 AuthService: Error de API', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('🔴 AuthService: Error general', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        try {
            // 1. Notificar a la API (si hay token)
            if ($this->currentToken) {
                $this->apiClient->post('/auth/logout');
            }
        } catch (ApiException $e) {
            // Ignorar errores en logout (no son críticos)
            Log::info('Error en logout (ignorado)', [
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Limpiar sesión local
        $this->clearSession();

        // 3. Limpiar autenticación de Laravel
        Auth::logout();

        // 4. Invalidar sesión de Laravel
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        // 5. Registrar logout
        Log::info('Usuario cerró sesión', [
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Refrescar token de autenticación
     */
    public function refreshToken(): string
    {
        try {
            $response = $this->apiClient->post('/auth/refresh');

            $newToken = $response['access_token'] ?? null;

            if (!$newToken) {
                throw new ApiException(
                    'No se pudo refrescar el token',
                    [],
                    500
                );
            }

            // Actualizar token
            $this->apiClient->setToken($newToken);
            $this->currentToken = $newToken;

            // Actualizar sesión
            Session::put('api_token', $newToken);

            return $newToken;
        } catch (ApiException $e) {
            // Si falla el refresh, limpiar sesión
            $this->clearSession();
            throw $e;
        }
    }

    /**
     * Cambiar contraseña del usuario autenticado
     */
    public function changePassword(string $currentPassword, string $newPassword): array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->apiClient->post('/auth/change-password', [
                'current_password' => $currentPassword,
                'new_password' => $newPassword,
                'new_password_confirmation' => $newPassword,
            ]);

            return $response;
        } catch (ApiException $e) {
            if ($e->getCode() === 422) {
                // La API devuelve errores de validación
                throw new ApiException(
                    $e->getMessage(),
                    $e->getErrors(),
                    422
                );
            }
            throw $e;
        }
    }

    /**
     * Cambiar empresa activa
     */
    public function switchCompany(int $companyId): array
    {
        $this->ensureAuthenticated();

        try {
            // 1. Notificar a la API
            $response = $this->apiClient->post('/auth/switch-company', [
                'company_id' => $companyId,
            ]);

            // 2. Actualizar contexto local
            $this->companyContext->setCurrentCompany($companyId);

            // 3. Actualizar usuario en sesión
            $user = Session::get('api_user');
            if ($user) {
                $user['current_company_id'] = $companyId;
                Session::put('api_user', $user);
                $this->currentUser = $user;
            }

            // 4. Actualizar empresa en ApiClient
            $this->apiClient->setCompanyId($companyId);

            // 5. Registrar cambio de empresa
            Log::info('Cambio de empresa', [
                'user_id' => $this->getCurrentUserId(),
                'company_id' => $companyId,
                'ip' => request()->ip(),
            ]);

            return [
                'success' => true,
                'message' => $response['message'] ?? 'Empresa cambiada exitosamente',
                'company_id' => $companyId,
                'user' => $this->currentUser,
            ];
        } catch (ApiException $e) {
            throw $e;
        }
    }

    /**
     * Obtener usuario actual
     */
    public function getCurrentUser(): ?array
    {
        if ($this->currentUser) {
            return $this->currentUser;
        }

        // Intentar cargar desde sesión
        $user = Session::get('api_user');
        if ($user) {
            $this->currentUser = $user;
            return $user;
        }

        // Intentar obtener desde la API (si hay token)
        if ($this->currentToken) {
            try {
                $response = $this->apiClient->get('/auth/me');
                $user = $response['user'] ?? $response['data']['user'] ?? null;

                if ($user) {
                    $this->saveUserToSession($user);
                    return $user;
                }
            } catch (ApiException $e) {
                // Si no se puede obtener, el token puede ser inválido
                Log::warning('No se pudo obtener usuario actual', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Obtener ID del usuario actual
     */
    public function getCurrentUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user['id'] ?? null;
    }

    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated(): bool
    {
        // Verificar token en sesión
        if (!$this->currentToken && !Session::has('api_token')) {
            return false;
        }

        // Verificar usuario en sesión
        if (!$this->getCurrentUser()) {
            return false;
        }

        // Verificar con la API (opcional, para mayor seguridad)
        try {
            $response = $this->apiClient->get('/auth/check');
            return $response['authenticated'] ?? false;
        } catch (ApiException $e) {
            // Si el token es inválido, limpiar sesión
            if ($e->getCode() === 401) {
                $this->clearSession();
                return false;
            }

            // Si hay otro error, asumir que está autenticado
            // pero loguear el error
            Log::warning('Error verificando autenticación', [
                'error' => $e->getMessage(),
            ]);
            return true;
        }
    }

    /**
     * Registrar un nuevo usuario
     */
    public function register(array $data): array
    {
        try {
            $response = $this->apiClient->post('/auth/register', $data);

            // Después del registro, el usuario puede necesitar verificar email
            return $response;
        } catch (ApiException $e) {
            throw $e;
        }
    }

    /**
     * Solicitar recuperación de contraseña
     */
    public function forgotPassword(string $email): array
    {
        try {
            return $this->apiClient->post('/auth/forgot-password', [
                'email' => $email,
            ]);
        } catch (ApiException $e) {
            throw $e;
        }
    }

    /**
     * Resetear contraseña con token
     */
    public function resetPassword(string $email, string $token, string $password): array
    {
        try {
            return $this->apiClient->post('/auth/reset-password', [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ]);
        } catch (ApiException $e) {
            throw $e;
        }
    }

    /**
     * Verificar email con token
     */
    public function verifyEmail(string $token): array
    {
        try {
            return $this->apiClient->post('/auth/verify-email', [
                'token' => $token,
            ]);
        } catch (ApiException $e) {
            throw $e;
        }
    }

    /**
     * Obtener empresas disponibles para el usuario
     */
    public function getAvailableCompanies(): array
    {
        $user = $this->getCurrentUser();
        return $user['companies'] ?? [];
    }

    /**
     * Obtener la empresa activa
     */
    public function getCurrentCompanyId(): ?int
    {
        return $this->companyContext->getCurrentCompanyId();
    }

    /**
     * Obtener el token actual
     */
    public function getToken(): ?string
    {
        return $this->currentToken;
    }

    /**
     * Verificar si el usuario tiene permisos para una acción
     * Nota: Esto es una verificación local, la autorización real está en el backend
     */
    public function hasPermission(string $permission): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        // Verificar si es super_admin (tiene todos los permisos)
        if (in_array('super_admin', $user['roles'] ?? [])) {
            return true;
        }

        // Verificar permisos específicos
        return in_array($permission, $user['permissions'] ?? []);
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        return in_array($role, $user['roles'] ?? []);
    }

    /**
     * Guardar usuario en sesión
     */
    private function saveUserToSession(array $user): void
    {
        // Normalizar datos del usuario
        $normalizedUser = $this->normalizeUserData($user);

        $this->currentUser = $normalizedUser;
        Session::put('api_user', $normalizedUser);

        // Si tiene empresa activa, actualizar contexto
        if (isset($normalizedUser['current_company_id'])) {
            $this->companyContext->setCurrentCompany($normalizedUser['current_company_id']);
            $this->apiClient->setCompanyId($normalizedUser['current_company_id']);
        }
    }

    /**
     * Normalizar datos de usuario para consistencia
     */
    private function normalizeUserData(array $user): array
    {
        return [
            'id' => $user['id'] ?? null,
            'name' => $user['name'] ?? $user['full_name'] ?? 'Usuario',
            'full_name' => $user['full_name'] ?? $user['name'] ?? 'Usuario',
            'email' => $user['email'] ?? null,
            'username' => $user['username'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'avatar_path' => $user['avatar_path'] ?? null,
            'is_active' => $user['is_active'] ?? true,
            'email_verified' => $user['email_verified'] ?? false,
            'email_verified_at' => $user['email_verified_at'] ?? null,
            'current_company_id' => $user['current_company_id'] ?? null,
            'companies' => $user['companies'] ?? [],
            'roles' => $user['roles'] ?? [],
            'permissions' => $user['permissions'] ?? [],
            'preferences' => $user['preferences'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null,
            'name_display' => $user['name_display'] ?? $user['name'] ?? null,
        ];
    }

    /**
     * Establecer empresa por defecto
     */
    private function setDefaultCompany(array $user): void
    {
        $companies = $user['companies'] ?? [];
        $currentCompany = $user['current_company'] ?? null;

        // Si ya tiene empresa activa en current_company
        if ($currentCompany && isset($currentCompany['id'])) {
            $this->companyContext->setCurrentCompany($currentCompany['id']);
            $this->apiClient->setCompanyId($currentCompany['id']);
            return;
        }

        // Si tiene current_company_id
        if (isset($user['current_company_id'])) {
            $this->companyContext->setCurrentCompany($user['current_company_id']);
            $this->apiClient->setCompanyId($user['current_company_id']);
            return;
        }

        // Si tiene una sola empresa, usarla automáticamente
        if (count($companies) === 1) {
            $companyId = $companies[0]['id'] ?? null;
            if ($companyId) {
                $this->companyContext->setCurrentCompany($companyId);
                $this->apiClient->setCompanyId($companyId);
            }
            return;
        }

        // Si tiene múltiples empresas, buscar la que sea default
        foreach ($companies as $company) {
            if (isset($company['pivot']['is_default']) && $company['pivot']['is_default'] == 1) {
                $this->companyContext->setCurrentCompany($company['id']);
                $this->apiClient->setCompanyId($company['id']);
                return;
            }
        }

        // Si no hay default, usar la primera
        if (!empty($companies)) {
            $companyId = $companies[0]['id'] ?? null;
            if ($companyId) {
                $this->companyContext->setCurrentCompany($companyId);
                $this->apiClient->setCompanyId($companyId);
            }
        }
    }

    /**
     * Autenticar usuario en Laravel (para Filament)
     */
    private function authenticateLaravelUser(array $userData): void
    {
        // Buscar o crear usuario en base de datos local
        $user = User::where('email', $userData['email'])->first();

        if (!$user) {
            // Crear usuario local para Filament (solo para autenticación)
            $user = User::create([
                'name' => $userData['name'] ?? $userData['full_name'] ?? 'Usuario',
                'email' => $userData['email'],
                'password' => bcrypt(str()->random(32)), // Contraseña aleatoria (no se usa)
                'email_verified' => $userData['email_verified'] ?? true,
                'email_verified_at' => ($userData['email_verified'] ?? false) ? now() : null,
                'is_active' => $userData['is_active'] ?? true,
            ]);
        }

        // Autenticar en Laravel
        Auth::login($user);
    }

    /**
     * Cargar datos desde sesión
     */
    private function loadFromSession(): void
    {
        // Cargar token
        $this->currentToken = Session::get('api_token');

        // Cargar usuario
        $this->currentUser = Session::get('api_user');

        // Si hay token, actualizar ApiClient
        if ($this->currentToken) {
            $this->apiClient->setToken($this->currentToken);
        }

        // Si hay empresa, actualizar ApiClient
        $companyId = $this->companyContext->getCurrentCompanyId();
        if ($companyId) {
            $this->apiClient->setCompanyId($companyId);
        }
    }

    /**
     * Limpiar sesión
     */
    private function clearSession(): void
    {
        $this->currentToken = null;
        $this->currentUser = null;

        Session::forget(['api_token', 'api_user']);

        $this->apiClient->clearToken();
        $this->companyContext->clear();
    }

    /**
     * Verificar que el usuario esté autenticado
     */
    private function ensureAuthenticated(): void
    {
        if (!$this->isAuthenticated()) {
            throw new ApiException(
                'Usuario no autenticado',
                [],
                401
            );
        }
    }
}
