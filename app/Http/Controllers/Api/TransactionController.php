<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Requests\Transaction\TransferRequest;
use App\Http\Requests\Transaction\ReconvertRequest;
use App\Services\TransactionService;
use App\Services\ReportService;
use App\Models\Transaction;
use App\DTOs\TransactionData;
use App\DTOs\TransferData;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use App\Traits\CachesBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses, CachesBalance;

    public function __construct(
        protected TransactionService $transactionService,
        protected ReportService $reportService,
    ) {}

    /**
     * GET /api/transactions
     * Listar transacciones con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->getCurrentCompanyId();
        $type = $request->type;

        $query = Transaction::with(['account', 'category', 'bankAccount', 'currency'])
            ->where('company_id', $companyId);

        if ($type && in_array($type, ['income', 'expense', 'transfer'])) {
            $query->where('type', $type);
        }

        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        if ($request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->bank_account_id) {
            $query->where('bank_account_id', $request->bank_account_id);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'LIKE', "%{$request->search}%")
                    ->orWhere('reference', 'LIKE', "%{$request->search}%");
            });
        }

        $transactions = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return $this->successResponse($transactions);
    }

    /**
     * POST /api/transactions
     * Crear una transacción (ingreso o egreso)
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = TransactionData::fromRequest($request);
        $transaction = $this->transactionService->create($data);

        // Registrar auditoría
        $this->logCreated('Transaction', $transaction->id, $transaction->toArray());

        // Invalidar caché de saldo
        if ($data->bankAccountId) {
            $this->invalidateBalanceCache($data->bankAccountId);
        }

        return $this->createdResponse($transaction, 'Transacción registrada correctamente');
    }

    /**
     * POST /api/transactions/transfer
     * Crear una transferencia entre cuentas
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $data = TransferData::fromRequest($request);
        $result = $this->transactionService->createTransfer($data);

        // Registrar auditoría
        $this->logCreated('Transfer', $result['expense']->id, $result);

        // Invalidar caché de saldos
        $this->invalidateBalanceCache($data->fromAccountId);
        $this->invalidateBalanceCache($data->toAccountId);

        return $this->createdResponse($result, 'Transferencia realizada correctamente');
    }

    /**
     * GET /api/transactions/{id}
     * Obtener una transacción específica
     */
    public function show(int $id): JsonResponse
    {
        $transaction = $this->findOrFailWithCompanyAccess(Transaction::class, $id);

        if ($transaction->isTransfer()) {
            $transaction->load(['linkedTransfer']);
        }

        return $this->successResponse($transaction);
    }

    /**
     * PUT /api/transactions/{id}
     * Actualizar una transacción
     */
    public function update(UpdateTransactionRequest $request, int $id): JsonResponse
    {
        $transaction = $this->findOrFailWithCompanyAccess(Transaction::class, $id);

        // Guardar datos anteriores para auditoría
        $oldData = $transaction->toArray();

        if ($transaction->is_reconciled) {
            return $this->errorResponse('No se puede modificar una transacción conciliada', 422);
        }

        $data = TransactionData::fromRequest($request);
        $updated = $this->transactionService->update($transaction, $data);

        // Registrar auditoría
        $this->logUpdated('Transaction', $id, $oldData, $updated->toArray());

        // Invalidar caché de saldo
        if ($data->bankAccountId) {
            $this->invalidateBalanceCache($data->bankAccountId);
        }

        return $this->successResponse($updated, 'Transacción actualizada correctamente');
    }

    /**
     * DELETE /api/transactions/{id}
     * Eliminar una transacción (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $transaction = $this->findOrFailWithCompanyAccess(Transaction::class, $id);

        if ($transaction->is_reconciled) {
            return $this->errorResponse('No se puede eliminar una transacción conciliada', 422);
        }

        // Guardar datos para auditoría e invalidación de caché
        $oldData = $transaction->toArray();
        $bankAccountId = $oldData['bank_account_id'];

        $this->transactionService->delete($transaction);

        // Registrar auditoría
        $this->logDeleted('Transaction', $id, $oldData);

        // Invalidar caché de saldo
        if ($bankAccountId) {
            $this->invalidateBalanceCache($bankAccountId);
        }

        return $this->deletedResponse('Transacción eliminada correctamente');
    }

    /**
     * GET /api/transactions/stats
     * Obtener estadísticas de transacciones
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $this->getCurrentCompanyId();
        $type = $request->type;

        $query = Transaction::where('company_id', $companyId);

        if ($type && in_array($type, ['income', 'expense'])) {
            $query->where('type', $type);
        }

        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        $stats = [
            'total' => $query->sum('amount'),
            'count' => $query->count(),
        ];

        return $this->successResponse($stats);
    }

    /**
     * GET /api/transactions/summary
     * Resumen del flujo de caja
     */
    public function summary(Request $request): JsonResponse
    {
        $companyId = $this->getCurrentCompanyId();
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $summary = $this->reportService->getCashFlowReport(
            $companyId,
            $startDate,
            $endDate,
            $request->category_id,
            $request->account_id
        );

        return $this->successResponse($summary);
    }
}