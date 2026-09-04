<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SwitchCompanyRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * POST /api/auth/register
     * Registrar un nuevo usuario y su empresa
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json($result, 201);
    }

    /**
     * POST /api/auth/login
     * Iniciar sesión
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            credentials: $request->validated(),
            deviceName: $request->header('User-Agent', 'web')
        );

        return response()->json($result);
    }

    /**
     * GET /api/auth/me
     * Obtener usuario autenticado
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        return response()->json([
            'user' => $user->load(['currentCompany', 'companies', 'preferences']),
        ]);
    }

    /**
     * POST /api/auth/logout
     * Cerrar sesión
     */
    public function logout(): JsonResponse
    {
        $user = auth()->user();

        if ($user) {
            $this->authService->logout($user);
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    /**
     * POST /api/auth/refresh
     * Refrescar token
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        // Revocar token actual
        $request->user()->currentAccessToken()->delete();

        // Crear nuevo token
        $token = $user->createToken($request->header('User-Agent', 'web'))->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * POST /api/auth/change-password
     * Cambiar contraseña
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        $result = $this->authService->changePassword(
            user: $user,
            currentPassword: $request->current_password,
            newPassword: $request->new_password
        );

        return response()->json($result);
    }

    /**
     * POST /api/auth/forgot-password
     * Solicitar recuperación de contraseña
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->requestPasswordReset($request->email);

        return response()->json($result);
    }

    /**
     * POST /api/auth/reset-password
     * Resetear contraseña
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->resetPassword(
            email: $request->email,
            token: $request->token,
            password: $request->password
        );

        return response()->json($result);
    }

    /**
     * POST /api/auth/switch-company
     * Cambiar empresa de contexto
     */
    public function switchCompany(SwitchCompanyRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        $result = $this->authService->switchCompany(
            user: $user,
            companyId: $request->company_id
        );

        return response()->json($result);
    }

    /**
     * GET /api/auth/check
     * Verificar autenticación (método de prueba)
     */
    public function checkAuth(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'authenticated' => false,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        return response()->json([
            'authenticated' => true,
            'user_id' => $user->id,
            'company_id' => $user->current_company_id,
            'email' => $user->email,
            'username' => $user->username,
            'full_name' => $user->name,
            'role' => $user->getRoleNames()->first() ?? 'user',
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * POST /api/auth/verify-email
     * Verificar email
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $result = $this->authService->verifyEmail($request->token);

        return response()->json($result);
    }
}