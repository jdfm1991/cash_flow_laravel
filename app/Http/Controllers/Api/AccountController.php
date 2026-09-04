<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Services\AccountService;
use App\Models\Account;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected AccountService $accountService,
    ) {}

    /**
     * GET /api/accounts
     * Listar cuentas del catálogo global
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $type = $request->input('type');
        $search = $request->input('search');

        // super_admin y admin ven TODAS las cuentas
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            $accounts = $this->accountService->getGlobalAccounts($type, $search);
        } else {
            // Usuario normal solo ve cuentas activas de su empresa
            $companyId = $user->current_company_id;
            $accounts = $this->accountService->getByCompany($companyId, $type, $search);
        }

        return $this->successResponse($accounts);
    }

    /**
     * GET /api/accounts/income
     * Listar cuentas de ingresos
     */
    public function getIncomeAccounts(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            $accounts = $this->accountService->getGlobalAccounts('income');
        } else {
            $companyId = $user->current_company_id;
            $accounts = $this->accountService->getByCompany($companyId, 'income');
        }

        return $this->successResponse($accounts);
    }

    /**
     * GET /api/accounts/expense
     * Listar cuentas de egresos
     */
    public function getExpenseAccounts(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            $accounts = $this->accountService->getGlobalAccounts('expense');
        } else {
            $companyId = $user->current_company_id;
            $accounts = $this->accountService->getByCompany($companyId, 'expense');
        }

        return $this->successResponse($accounts);
    }

    /**
     * GET /api/accounts/{id}
     * Obtener cuenta específica
     */
    public function show(int $id): JsonResponse
    {
        $account = $this->accountService->findById($id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta no encontrada');
        }

        return $this->successResponse($account->load('category'));
    }

    /**
     * POST /api/accounts
     * Crear nueva cuenta (solo super_admin)
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para crear cuentas');
        }

        $account = $this->accountService->create($request->validated());

        // ✅ Auditoría
        $this->logCreated('Account', $account->id, $account->toArray());

        return $this->createdResponse($account, 'Cuenta creada exitosamente');
    }

    /**
     * PUT /api/accounts/{id}
     * Actualizar cuenta (solo super_admin)
     */
    public function update(UpdateAccountRequest $request, int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar cuentas');
        }

        $account = $this->accountService->findById($id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta no encontrada');
        }

        // ✅ Si es cuenta del sistema, no se puede modificar
        if ($account->is_system) {
            return $this->errorResponse('No se puede modificar una cuenta del sistema', 422);
        }

        $oldData = $account->toArray();
        $updated = $this->accountService->update($account, $request->validated());

        // ✅ Auditoría
        $this->logUpdated('Account', $id, $oldData, $updated->toArray());

        return $this->successResponse($updated, 'Cuenta actualizada exitosamente');
    }

    /**
     * DELETE /api/accounts/{id}
     * Eliminar cuenta (solo super_admin)
     */
    public function destroy(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para eliminar cuentas');
        }

        $account = $this->accountService->findById($id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta no encontrada');
        }

        // ✅ Si es cuenta del sistema, no se puede eliminar
        if ($account->is_system) {
            return $this->errorResponse('No se puede eliminar una cuenta del sistema', 422);
        }

        $oldData = $account->toArray();

        try {
            $this->accountService->delete($account);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        // ✅ Auditoría
        $this->logDeleted('Account', $id, $oldData);

        return $this->deletedResponse('Cuenta eliminada exitosamente');
    }

    /**
     * GET /api/accounts/category/{categoryId}
     * Obtener cuentas por categoría
     */
    public function getByCategory(int $categoryId, Request $request): JsonResponse
    {
        $onlyActive = $request->boolean('active', true);
        $accounts = $this->accountService->getByCategory($categoryId, $onlyActive);

        return $this->successResponse($accounts);
    }

    /**
     * GET /api/accounts/search
     * Buscar cuentas por nombre
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $accounts = $this->accountService->search(
            $request->input('q'),
            $request->input('type')
        );

        return $this->successResponse($accounts);
    }

    /**
     * POST /api/accounts/{id}/toggle
     * Activar/desactivar cuenta
     */
    public function toggle(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para gestionar cuentas');
        }

        $account = $this->accountService->findById($id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta no encontrada');
        }

        // ✅ Si es cuenta del sistema y está activa, no se puede desactivar
        if ($account->is_system && $account->is_active) {
            return $this->errorResponse('No se puede desactivar una cuenta del sistema', 422);
        }

        $oldData = $account->toArray();
        $this->accountService->toggle($account);
        $account->refresh();

        // ✅ Auditoría
        $this->logUpdated('Account', $id, $oldData, $account->toArray());

        $status = $account->is_active ? 'activada' : 'desactivada';

        return $this->successResponse($account, "Cuenta {$status} exitosamente");
    }

    /**
     * GET /api/accounts/statistics
     * Estadísticas de cuentas
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->accountService->getStats();

        return $this->successResponse($stats);
    }

    /**
     * Verificar permisos para gestionar cuentas
     */
    private function canManageAccounts(): bool
    {
        $user = auth()->user();
        return $user->hasRole('super_admin');
    }
}