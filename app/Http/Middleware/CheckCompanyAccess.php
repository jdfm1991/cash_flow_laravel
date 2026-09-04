<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Traits\HandlesApiResponses;

class CheckCompanyAccess
{
    use HandlesApiResponses;

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse('Usuario no autenticado');
        }

        $companyId = $this->resolveCompanyId($request, $user);

        if (!$companyId) {
            return $this->errorResponse('No se especificó una empresa', 400);
        }

        // ✅ Validar que la empresa existe y está activa
        $company = Company::find($companyId);

        if (!$company) {
            return $this->errorResponse('Empresa no encontrada', 404);
        }

        if (!$company->is_active) {
            return $this->errorResponse('Empresa no disponible', 403);
        }

        // ✅ Super_admin bypass DESPUÉS de validar existencia
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        if (!$user->belongsToCompany($companyId)) {
            return $this->errorResponse('No tienes acceso a esta empresa', 403);
        }

        return $next($request);
    }

    private function resolveCompanyId(Request $request, $user): ?int
    {
        return $request->input('company_id') 
            ?? $request->route('company_id') 
            ?? $request->header('X-Company-Id')
            ?? $user->current_company_id;
    }
}