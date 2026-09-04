<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bank\StoreBankRequest;
use App\Http\Requests\Bank\UpdateBankRequest;
use App\Services\BankService;
use App\Models\Bank;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\Request;

class BankController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected BankService $bankService,
    ) {}

    /**
     * GET /api/banks
     */
    public function index(Request $request)
    {
        $banks = $this->bankService->getAll(
            onlyActive: $request->boolean('active', true),
            countryCode: $request->input('country'),
            search: $request->input('search'),
            perPage: $request->input('per_page', 25)
        );

        return $this->successResponse($banks);
    }

    /**
     * GET /api/banks/{id}
     */
    public function show(int $id)
    {
        $bank = $this->bankService->findById($id);

        if (!$bank) {
            return $this->notFoundResponse('Banco no encontrado');
        }

        return $this->successResponse($bank);
    }

    /**
     * POST /api/banks
     * ✅ Verificación DIRECTA - SIN authorize()
     */
    public function store(StoreBankRequest $request)
    {
        // ✅ Verificación directa de rol
        if (!auth()->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('No tienes permisos para crear bancos');
        }

        $bank = $this->bankService->create($request->validated());

        $this->logCreated('Bank', $bank->id, $bank->toArray());

        return $this->createdResponse($bank, 'Banco creado exitosamente');
    }

    /**
     * PUT /api/banks/{id}
     * ✅ Verificación DIRECTA - SIN authorize()
     */
    public function update(UpdateBankRequest $request, int $id)
    {
        // ✅ Verificación directa de rol
        if (!auth()->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('No tienes permisos para actualizar bancos');
        }

        $bank = $this->bankService->findById($id);

        if (!$bank) {
            return $this->notFoundResponse('Banco no encontrado');
        }

        $oldData = $bank->toArray();
        $updated = $this->bankService->update($bank, $request->validated());

        $this->logUpdated('Bank', $id, $oldData, $updated->toArray());

        return $this->successResponse($updated, 'Banco actualizado exitosamente');
    }

    /**
     * DELETE /api/banks/{id}
     * ✅ Verificación DIRECTA - SIN authorize()
     */
    public function destroy(int $id)
    {
        // ✅ Verificación directa de rol
        if (!auth()->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('No tienes permisos para eliminar bancos');
        }

        $bank = $this->bankService->findById($id);

        if (!$bank) {
            return $this->notFoundResponse('Banco no encontrado');
        }

        $oldData = $bank->toArray();
        $this->bankService->delete($bank);

        $this->logDeleted('Bank', $id, $oldData);

        return $this->deletedResponse('Banco eliminado exitosamente');
    }

    /**
     * GET /api/banks/search
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $banks = $this->bankService->search(
            $request->input('q'),
            $request->input('limit', 10)
        );

        return $this->successResponse($banks);
    }

    /**
     * GET /api/banks/countries
     */
    public function countries(Request $request)
    {
        $countries = $this->bankService->getCountries(
            $request->boolean('only_active', true)
        );

        return $this->successResponse($countries);
    }

    /**
     * POST /api/banks/{id}/toggle
     * ✅ Verificación DIRECTA - SIN authorize()
     */
    public function toggle(Request $request, int $id)
    {
        // ✅ Verificación directa de rol
        if (!auth()->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('No tienes permisos para gestionar bancos');
        }

        $bank = $this->bankService->findById($id);

        if (!$bank) {
            return $this->notFoundResponse('Banco no encontrado');
        }

        $oldData = $bank->toArray();
        $bank->update(['is_active' => !$bank->is_active]);
        $bank->refresh();

        $this->logUpdated('Bank', $id, $oldData, $bank->toArray());

        $status = $bank->is_active ? 'activado' : 'desactivado';

        return $this->successResponse($bank, "Banco {$status} exitosamente");
    }

    /**
     * GET /api/banks/options
     */
    public function options(Request $request)
    {
        $banks = $this->bankService->getOptions(
            $request->boolean('only_active', true)
        );

        return $this->successResponse($banks);
    }
}