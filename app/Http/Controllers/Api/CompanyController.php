<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    /**
     * GET /api/companies
     * Listar empresas
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            $companies = Company::when($request->search, function ($query, $search) {
                return $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('business_name', 'LIKE', "%{$search}%");
            })
                ->orderBy('name')
                ->paginate($request->per_page ?? 25);
        } else {
            $companies = $user->companies()
                ->when($request->search, function ($query, $search) {
                    return $query->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('business_name', 'LIKE', "%{$search}%");
                })
                ->orderBy('name')
                ->paginate($request->per_page ?? 25);
        }

        return $this->successResponse($companies);
    }

    /**
     * GET /api/companies/me
     * Obtener la empresa actual del usuario
     */
    public function myCompany(Request $request)
    {
        $company = $this->getCurrentCompany();

        if (!$company) {
            return $this->errorResponse('No tienes una empresa activa', 404);
        }

        // ✅ Cargar relaciones adicionales
        $company->load(['bankAccounts', 'subscriptionPlan']);

        return $this->successResponse($company);
    }

    /**
     * GET /api/companies/{id}
     * Obtener empresa específica
     * 
     * ✅ Acepta int o string (por si viene "me" por error)
     */
    public function show($id)  // ← Sin type hint para aceptar int o string
    {
        // ✅ Si viene "me", redirigir al método myCompany
        if ($id === 'me' || $id === 'current') {
            return $this->myCompany(request());
        }

        $user = auth()->user();

        // Super admin puede ver cualquier empresa
        if ($user->hasRole('super_admin')) {
            $company = Company::with(['bankAccounts', 'subscriptionPlan'])
                ->find($id);
        } else {
            // Usuario normal solo puede ver sus empresas
            $company = $user->companies()
                ->with(['bankAccounts', 'subscriptionPlan'])
                ->find($id);
        }

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        return $this->successResponse($company);
    }

    /**
     * POST /api/companies
     * Crear empresa
     */
    public function store(StoreCompanyRequest $request)
    {
        $this->authorize('create', Company::class);

        $company = Company::create($request->validated());

        // Asignar el usuario creador a la empresa
        $company->users()->attach(auth()->id(), [
            'is_default' => true,
            'joined_at' => now(),
        ]);

        $this->logCreated('Company', $company->id, $company->toArray());

        return $this->createdResponse($company, 'Empresa creada exitosamente');
    }

    /**
     * PUT /api/companies/{id}
     * Actualizar empresa
     */
    public function update(UpdateCompanyRequest $request, int $id)
    {
        $company = Company::find($id);

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        $this->authorize('update', $company);

        $oldData = $company->toArray();
        $company->update($request->validated());
        $company->refresh();

        $this->logUpdated('Company', $id, $oldData, $company->toArray());

        return $this->successResponse($company, 'Empresa actualizada exitosamente');
    }

    /**
     * DELETE /api/companies/{id}
     * Eliminar empresa (solo super_admin)
     */
    public function destroy(int $id)
    {
        $company = Company::find($id);

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        $this->authorize('delete', $company);

        $oldData = $company->toArray();
        $company->delete();

        $this->logDeleted('Company', $id, $oldData);

        return $this->deletedResponse('Empresa eliminada exitosamente');
    }

    /**
     * POST /api/companies/{id}/logo
     * Subir logo de empresa
     */
    public function uploadLogo(Request $request, int $id)
    {
        $company = Company::find($id);

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        $this->authorize('update', $company);

        $request->validate([
            'logo' => 'required|image|max:2048|dimensions:max_width=500,max_height=500',
        ]);

        $path = $request->file('logo')->store('company-logos', 'public');
        $company->update(['logo_path' => $path]);

        return $this->successResponse([
            'logo_url' => asset('storage/' . $path),
        ], 'Logo actualizado exitosamente');
    }

    /**
     * DELETE /api/companies/{id}/logo
     * Eliminar logo de empresa
     */
    public function deleteLogo(int $id)
    {
        $company = Company::find($id);

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        $this->authorize('update', $company);

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $company->update(['logo_path' => null]);
        }

        return $this->deletedResponse('Logo eliminado exitosamente');
    }

    /**
     * GET /api/companies/{id}/stats
     * Obtener estadísticas de la empresa
     */
    public function stats(int $id)
    {
        $company = Company::find($id);

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        $this->authorize('view', $company);

        $stats = [
            'total_users' => $company->users()->count(),
            'total_bank_accounts' => $company->bankAccounts()->count(),
            'total_transactions' => $company->transactions()->count(),
            'total_income' => $company->transactions()
                ->where('type', 'income')
                ->sum('amount'),
            'total_expense' => $company->transactions()
                ->where('type', 'expense')
                ->sum('amount'),
            'balance' => $company->transactions()
                ->where('type', 'income')
                ->sum('amount') -
                $company->transactions()
                ->where('type', 'expense')
                ->sum('amount'),
        ];

        return $this->successResponse($stats);
    }

    /**
     * POST /api/companies/{id}/subscription
     * Cambiar plan de suscripción
     */
    public function changeSubscription(Request $request, int $id)
    {
        $company = Company::find($id);

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        $this->authorize('update', $company);

        $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $oldData = $company->toArray();
        $company->update([
            'subscription_plan_id' => $request->subscription_plan_id,
        ]);
        $company->refresh();

        $this->logUpdated('Company', $id, $oldData, $company->toArray());

        return $this->successResponse($company, 'Plan de suscripción actualizado');
    }
}
