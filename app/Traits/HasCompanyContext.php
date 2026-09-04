<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait HasCompanyContext
{
    /**
     * Obtener el ID de la empresa actual del usuario
     */
    public function getCurrentCompanyId(): int
    {
        return auth()->user()->current_company_id;
    }

    /**
     * Obtener la empresa actual del usuario
     */
    public function getCurrentCompany(): ?Company
    {
        return auth()->user()->currentCompany;
    }

    /**
     * Validar que el usuario tenga acceso a una empresa específica
     */
    public function hasCompanyAccess(int $companyId): bool
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->belongsToCompany($companyId);
    }

    /**
     * Validar que una entidad pertenezca a la empresa actual
     */
    public function validateCompanyOwnership(Model $model, string $companyColumn = 'company_id'): bool
    {
        return $model->$companyColumn === $this->getCurrentCompanyId();
    }

    /**
     * Obtener una entidad verificando que pertenezca a la empresa actual
     */
    public function findWithCompanyAccess(string $modelClass, int $id, string $companyColumn = 'company_id'): ?Model
    {
        $companyId = $this->getCurrentCompanyId();

        return $modelClass::where('id', $id)
            ->where($companyColumn, $companyId)
            ->first();
    }

    /**
     * Verificar que el usuario tiene acceso y obtener la entidad
     * Si no tiene acceso, devuelve una respuesta JSON de error
     */
    public function findOrFailWithCompanyAccess(string $modelClass, int $id, string $companyColumn = 'company_id'): ?Model
    {
        $model = $this->findWithCompanyAccess($modelClass, $id, $companyColumn);

        if (!$model) {
            abort(404, 'Recurso no encontrado');
        }

        return $model;
    }

    /**
     * Obtener company_id de la solicitud (prioridad: request > usuario)
     */
    public function resolveCompanyId($request = null): int
    {
        $request = $request ?? request();

        // Si es super_admin y se envía company_id en la solicitud
        if (auth()->user()->hasRole('super_admin') && $request->has('company_id')) {
            return (int) $request->company_id;
        }

        return $this->getCurrentCompanyId();
    }

    /**
     * Verificar si el usuario puede ver todas las empresas (super_admin)
     */
    public function canViewAllCompanies(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    /**
     * Asegurar que el usuario tiene una empresa activa
     */
    public function ensureCompanyContext(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->current_company_id) {
            return response()->json([
                'message' => 'Debes seleccionar una empresa para continuar',
                'companies' => $user->companies->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ]),
            ], 400);
        }

        // ✅ Sugerencia: Verificar que la empresa aún existe y está activa
        $company = Company::find($user->current_company_id);
        if (!$company || !$company->is_active) {
            return response()->json([
                'message' => 'La empresa seleccionada no está disponible',
            ], 403);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Validar que una empresa existe y está activa
     */
    public function ensureCompanyIsActive(int $companyId): bool
    {
        $company = Company::find($companyId);
        return $company && $company->is_active;
    }

    /**
     * Obtener empresa actual con validación
     */
    public function getCurrentCompanyOrFail(): Company
    {
        $company = $this->getCurrentCompany();

        if (!$company || !$company->is_active) {
            abort(403, 'Empresa no disponible');
        }

        return $company;
    }

    /**
     * Resolver company_id desde la request
     * Prioridad: request > usuario
     * Super_admin puede especificar company_id
     */
    public function resolveCompanyIdFromRequest(Request $request): int
    {
        $user = auth()->user();

        // Si es super_admin y se especifica company_id, usar esa
        if ($user->hasRole('super_admin') && $request->has('company_id')) {
            $companyId = (int) $request->company_id;
            $company = \App\Models\Company::find($companyId);
            if ($company && $company->is_active) {
                return $companyId;
            }
        }

        return $user->current_company_id ?? 0;
    }
}
