<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\AssignRoleRequest;
use App\Services\UserService;
use App\Models\User;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected UserService $userService,
    ) {}

    /**
     * GET /api/users
     * Listar todos los usuarios (solo super_admin)
     */
    public function index(): JsonResponse
    {
        $users = User::with(['companies', 'roles', 'preferences'])
            ->orderBy('name')  // ✅ Cambio: full_name → name
            ->get();

        return $this->successResponse($users);
    }

    /**
     * GET /api/users/company
     * Listar usuarios de la empresa actual
     */
    public function getByCompany(): JsonResponse
    {
        $companyId = $this->getCurrentCompanyId();

        $users = $this->userService->getByCompany($companyId);

        return $this->successResponse($users);
    }

    /**
     * GET /api/users/{id}
     * Obtener usuario específico
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->findById($id);

        if (!$user) {
            return $this->notFoundResponse('Usuario no encontrado');
        }

        return $this->successResponse($user->load(['companies', 'roles', 'permissions', 'preferences']));
    }

    /**
     * POST /api/users
     * Crear nuevo usuario (solo super_admin)
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create(
            data: $request->validated(),
            createdBy: auth()->id(),
        );

        $this->logCreated('User', $user->id, $user->toArray());

        return $this->createdResponse($user, 'Usuario creado exitosamente');
    }

    /**
     * PUT /api/users/{id}
     * Actualizar usuario
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->findById($id);

        if (!$user) {
            return $this->notFoundResponse('Usuario no encontrado');
        }

        $oldData = $user->toArray();

        $updated = $this->userService->update($user, $request->validated());

        $this->logUpdated('User', $id, $oldData, $updated->toArray());

        return $this->successResponse($updated, 'Usuario actualizado exitosamente');
    }

    /**
     * DELETE /api/users/{id}
     * Eliminar usuario
     */
    public function destroy(int $id): JsonResponse
    {
        $user = $this->userService->findById($id);

        if (!$user) {
            return $this->notFoundResponse('Usuario no encontrado');
        }

        if ($id === auth()->id()) {
            return $this->errorResponse('No puedes eliminar tu propio usuario', 422);
        }

        $oldData = $user->toArray();

        $this->userService->delete($user);

        $this->logDeleted('User', $id, $oldData);

        return $this->deletedResponse('Usuario eliminado exitosamente');
    }

    /**
     * POST /api/users/{id}/toggle
     * Activar/desactivar usuario
     */
    public function toggle(int $id): JsonResponse
    {
        $user = $this->userService->findById($id);

        if (!$user) {
            return $this->notFoundResponse('Usuario no encontrado');
        }

        $oldData = $user->toArray();

        $this->userService->toggleActive($user);

        $this->logUpdated('User', $id, $oldData, $user->toArray());

        return $this->successResponse($user->fresh(), $user->is_active ? 'Usuario activado' : 'Usuario desactivado');
    }

    /**
     * POST /api/users/{id}/assign-role
     * Asignar rol a usuario
     */
    public function assignRole(AssignRoleRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->findById($id);

        if (!$user) {
            return $this->notFoundResponse('Usuario no encontrado');
        }

        $oldRoles = $user->getRoleNames()->toArray();

        $this->userService->assignRole($user, $request->role);

        $this->logUpdated('User', $id, ['roles' => $oldRoles], ['roles' => $user->getRoleNames()->toArray()]);

        return $this->successResponse($user->fresh()->load('roles'), 'Rol asignado exitosamente');
    }

    /**
     * POST /api/users/{id}/remove-role
     * Remover rol de usuario
     */
    public function removeRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = $this->userService->findById($id);

        if (!$user) {
            return $this->notFoundResponse('Usuario no encontrado');
        }

        $oldRoles = $user->getRoleNames()->toArray();

        $this->userService->removeRole($user, $request->role);

        $this->logUpdated('User', $id, ['roles' => $oldRoles], ['roles' => $user->getRoleNames()->toArray()]);

        return $this->successResponse($user->fresh()->load('roles'), 'Rol removido exitosamente');
    }

    /**
     * GET /api/users/preferences
     * Obtener preferencias del usuario autenticado
     */
    public function getPreferences(): JsonResponse
    {
        $user = auth()->user();
        $preferences = $this->userService->getPreferences($user);

        return $this->successResponse($preferences);
    }

    /**
     * PUT /api/users/preferences
     * Actualizar preferencias del usuario autenticado
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'nullable|string|in:es,en,pt',
            'timezone' => 'nullable|string|max:50',
            'theme' => 'nullable|string|in:light,dark,system',
            'notifications_email' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $preferences = $this->userService->updatePreferences($user, $request->all());

        return $this->successResponse($preferences, 'Preferencias actualizadas exitosamente');
    }

    /**
     * GET /api/users/stats
     * Estadísticas de usuarios (solo super_admin)
     */
    public function stats(): JsonResponse
    {
        $stats = $this->userService->getStats();

        return $this->successResponse($stats);
    }
}