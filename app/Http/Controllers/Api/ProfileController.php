<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Services\UserService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /**
     * GET /api/profile
     * Obtener perfil del usuario autenticado
     */
    public function show(): JsonResponse
    {
        $user = auth()->user()->load([
            'currentCompany',
            'companies',
            'preferences',
            'roles',
            'permissions',
        ]);

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'avatar' => $user->avatar ? Storage::url($user->avatar) : null,
            'is_active' => $user->is_active,
            'email_verified' => $user->email_verified,
            'last_login_at' => $user->last_login,
            'current_company' => $user->currentCompany ? [
                'id' => $user->currentCompany->id,
                'name' => $user->currentCompany->name,
                'logo' => $user->currentCompany->logo_url,
            ] : null,
            'companies' => $user->companies->map(function ($company) use ($user) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'is_default' => $user->companies()->wherePivot('is_default', true)->exists(),
                ];
            }),
            'preferences' => $user->preferences,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    /**
     * PUT /api/profile
     * Actualizar perfil del usuario autenticado
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();

        $data = $request->validated();

        // Si se envía full_name, actualizar
        if (isset($data['full_name'])) {
            $user->full_name = $data['full_name'];
        }

        // Si se envía email, verificar que no esté en uso
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $existing = User::where('email', $data['email'])->first();
            if ($existing) {
                return response()->json([
                    'message' => 'El email ya está registrado por otro usuario',
                ], 422);
            }
            $user->email = $data['email'];
        }

        // Si se envía username, verificar que no esté en uso
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $existing = User::where('username', $data['username'])->first();
            if ($existing) {
                return response()->json([
                    'message' => 'El nombre de usuario ya está en uso',
                ], 422);
            }
            $user->username = $data['username'];
        }

        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * POST /api/profile/avatar
     * Actualizar avatar del usuario autenticado
     */
    public function uploadAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Eliminar avatar anterior si existe
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Guardar nuevo avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar = $path;
        $user->save();

        return response()->json([
            'message' => 'Avatar actualizado exitosamente',
            'avatar_url' => Storage::url($path),
        ]);
    }

    /**
     * DELETE /api/profile/avatar
     * Eliminar avatar del usuario autenticado
     */
    public function deleteAvatar(): JsonResponse
    {
        $user = auth()->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->avatar = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Avatar eliminado exitosamente',
        ]);
    }

    /**
     * POST /api/profile/change-password
     * Cambiar contraseña del usuario autenticado
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Verificar contraseña actual
        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta',
            ], 422);
        }

        // Actualizar contraseña
        $user->password = bcrypt($request->new_password);
        $user->save();

        // Opcional: revocar todos los tokens excepto el actual
        // $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente',
        ]);
    }

    /**
     * GET /api/profile/activity
     * Obtener actividad reciente del usuario autenticado
     */
    public function activity(): JsonResponse
    {
        $user = auth()->user();

        // Obtener logs de auditoría del usuario
        $logs = \App\Models\AuditLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                return [
                    'action' => $log->action,
                    'action_label' => $log->action_label,
                    'entity_type' => $log->entity_type,
                    'entity_type_label' => $log->entity_type_label,
                    'created_at' => $log->created_at,
                ];
            });

        return response()->json($logs);
    }

    /**
     * GET /api/profile/sessions
     * Obtener sesiones activas del usuario autenticado
     */
    public function sessions(): JsonResponse
    {
        $user = auth()->user();

        $sessions = $user->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'device' => $token->name ?? 'Desconocido',
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'is_current' => $token->id === auth()->user()->currentAccessToken()?->id,
                ];
            });

        return response()->json($sessions);
    }

    /**
     * DELETE /api/profile/sessions/{id}
     * Revocar una sesión específica
     */
    public function revokeSession(int $id): JsonResponse
    {
        $user = auth()->user();

        // No permitir revocar la sesión actual
        if ($id === $user->currentAccessToken()?->id) {
            return response()->json([
                'message' => 'No puedes revocar tu sesión actual',
            ], 422);
        }

        $token = $user->tokens()->find($id);

        if (!$token) {
            return response()->json([
                'message' => 'Sesión no encontrada',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Sesión revocada exitosamente',
        ]);
    }

    /**
     * DELETE /api/profile/sessions
     * Revocar todas las sesiones excepto la actual
     */
    public function revokeAllSessions(): JsonResponse
    {
        $user = auth()->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => 'Todas las sesiones han sido revocadas excepto la actual',
        ]);
    }
}