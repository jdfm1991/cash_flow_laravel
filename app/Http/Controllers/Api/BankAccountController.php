<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccount\StoreBankAccountRequest;
use App\Http\Requests\BankAccount\UpdateBankAccountRequest;
use App\Services\BankAccountService;
use App\Services\BalanceService;
use App\Models\BankAccount;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use App\Traits\CachesBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses, CachesBalance;

    public function __construct(
        protected BankAccountService $bankAccountService,
        protected BalanceService $balanceService,
    ) {}

    /**
     * GET /api/bank-accounts
     * Listar cuentas bancarias de la empresa
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->getCurrentCompanyId();
        $onlyActive = $request->boolean('active', true);

        $accounts = $this->bankAccountService->getByCompany($companyId, $onlyActive);

        return $this->successResponse($accounts);
    }

    /**
     * GET /api/bank-accounts/{id}
     * Obtener cuenta bancaria específica
     */
    public function show(int $id): JsonResponse
    {
        $account = $this->findWithCompanyAccess(BankAccount::class, $id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta bancaria no encontrada');
        }

        // Cargar relaciones y saldo
        $account->load(['bank', 'currency']);
        $account->current_balance = $this->balanceService->getBalance($account);

        return $this->successResponse($account);
    }

    /**
     * POST /api/bank-accounts
     * Crear nueva cuenta bancaria
     */
    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $companyId = $this->getCurrentCompanyId();

        // ✅ Verificar permisos
        if (!$this->canManageBankAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para crear cuentas bancarias');
        }

        $data = $request->toDto($companyId);
        $account = $this->bankAccountService->create($data);

        // ✅ Auditoría
        $this->logCreated('BankAccount', $account->id, $account->toArray());

        return $this->createdResponse($account, 'Cuenta bancaria creada exitosamente');
    }

    /**
     * PUT /api/bank-accounts/{id}
     * Actualizar cuenta bancaria
     */
    public function update(UpdateBankAccountRequest $request, int $id): JsonResponse
    {
        // ✅ Verificar permisos
        if (!$this->canManageBankAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar cuentas bancarias');
        }

        $account = $this->findWithCompanyAccess(BankAccount::class, $id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta bancaria no encontrada');
        }

        $oldData = $account->toArray();
        $data = $request->toDto();
        $updated = $this->bankAccountService->update($account, $data);

        // ✅ Auditoría
        $this->logUpdated('BankAccount', $id, $oldData, $updated->toArray());

        // ✅ Invalidar caché
        $this->invalidateBalanceCache($id);

        return $this->successResponse($updated, 'Cuenta bancaria actualizada exitosamente');
    }

    /**
     * DELETE /api/bank-accounts/{id}
     * Eliminar cuenta bancaria
     */
    public function destroy(int $id): JsonResponse
    {
        // ✅ Verificar permisos
        if (!$this->canManageBankAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para eliminar cuentas bancarias');
        }

        $account = $this->findWithCompanyAccess(BankAccount::class, $id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta bancaria no encontrada');
        }

        $oldData = $account->toArray();

        try {
            $this->bankAccountService->delete($account);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        // ✅ Auditoría
        $this->logDeleted('BankAccount', $id, $oldData);

        // ✅ Invalidar caché
        $this->invalidateBalanceCache($id);

        return $this->deletedResponse('Cuenta bancaria eliminada exitosamente');
    }

    /**
     * GET /api/bank-accounts/{id}/balance
     * Obtener saldo de una cuenta
     */
    public function balance(int $id): JsonResponse
    {
        $account = $this->findWithCompanyAccess(BankAccount::class, $id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta bancaria no encontrada');
        }

        $balance = $this->getCachedBalance($id);

        return $this->successResponse([
            'account_id' => $account->id,
            'account_number' => $account->account_number,
            'alias' => $account->alias,
            'opening_balance' => $account->opening_balance,
            'current_balance' => $balance,
            'currency' => $account->currency?->code,
            'currency_symbol' => $account->currency?->symbol,
        ]);
    }

    /**
     * GET /api/bank-accounts/{id}/balance-history
     * Obtener historial de saldo en un rango de fechas
     */
    public function balanceHistory(Request $request, int $id): JsonResponse
    {
        $account = $this->findWithCompanyAccess(BankAccount::class, $id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta bancaria no encontrada');
        }

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        // Obtener saldos diarios
        $history = [];
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($current <= $end) {
            $date = $current->toDateString();
            $history[] = [
                'date' => $date,
                'balance' => $this->getCachedBalanceOnDate($id, $date),
            ];
            $current->addDay();
        }

        return $this->successResponse([
            'account_id' => $account->id,
            'alias' => $account->alias,
            'account_number' => $account->account_number,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'history' => $history,
        ]);
    }

    /**
     * GET /api/bank-accounts/summary
     * Resumen de todas las cuentas de la empresa
     */
    public function summary(Request $request): JsonResponse
    {
        $companyId = $this->getCurrentCompanyId();
        $summary = $this->bankAccountService->getSummary($companyId);

        return $this->successResponse($summary);
    }

    /**
     * POST /api/bank-accounts/{id}/toggle
     * Activar/desactivar cuenta bancaria
     */
    public function toggle(int $id): JsonResponse
    {
        // ✅ Verificar permisos
        if (!$this->canManageBankAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para gestionar cuentas bancarias');
        }

        $account = $this->findWithCompanyAccess(BankAccount::class, $id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta bancaria no encontrada');
        }

        $oldData = $account->toArray();
        $updated = $this->bankAccountService->toggle($account);

        // ✅ Auditoría
        $this->logUpdated('BankAccount', $id, $oldData, $updated->toArray());

        // ✅ Invalidar caché
        $this->invalidateBalanceCache($id);

        $status = $updated->is_active ? 'activada' : 'desactivada';

        return $this->successResponse($updated, "Cuenta bancaria {$status} exitosamente");
    }

    /**
     * POST /api/bank-accounts/{id}/set-default
     * Establecer cuenta como predeterminada
     */
    public function setDefault(int $id): JsonResponse
    {
        // ✅ Verificar permisos
        if (!$this->canManageBankAccounts()) {
            return $this->forbiddenResponse('No tienes permisos para gestionar cuentas bancarias');
        }

        $account = $this->findWithCompanyAccess(BankAccount::class, $id);

        if (!$account) {
            return $this->notFoundResponse('Cuenta bancaria no encontrada');
        }

        $oldData = $account->toArray();
        $updated = $this->bankAccountService->setDefault($account);

        // ✅ Auditoría
        $this->logUpdated('BankAccount', $id, $oldData, $updated->toArray());

        return $this->successResponse($updated, 'Cuenta bancaria establecida como predeterminada');
    }

    /**
     * Verificar permisos para gestionar cuentas bancarias
     */
    private function canManageBankAccounts(): bool
    {
        $user = auth()->user();
        return $user->hasRole('super_admin') ||
               $user->hasRole('admin') ||
               $user->hasRole('accountant');
    }
}