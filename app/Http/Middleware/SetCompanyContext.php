<?php

namespace App\Http\Middleware;

use App\Services\Context\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SetCompanyContext
{
    public function __construct(
        private CompanyContext $companyContext
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // ✅ Verificar que el usuario está autenticado
        $user = Auth::user();
        if (!$user) {
            Log::info('SetCompanyContext: No hay usuario autenticado');
            return $next($request);
        }

        // ✅ Cargar empresas disponibles del usuario
        $companies = $user->companies()->get()->toArray();
        $this->companyContext->setAvailableCompanies($companies);

        // ✅ Si no tiene empresas, redirigir
        if (empty($companies)) {
            return redirect()->to('/admin/login')
                ->with('error', 'Usuario sin empresas asignadas.');
        }

        // ✅ Establecer la empresa
        if ($user->current_company_id) {
            // Verificar que la empresa actual sea válida
            $companyIds = array_column($companies, 'id');
            if (!in_array($user->current_company_id, $companyIds)) {
                // Si la empresa actual no es válida, usar la primera
                $this->companyContext->setCurrentCompany($companies[0]['id']);
                $user->current_company_id = $companies[0]['id'];
                $user->save();
            } else {
                $this->companyContext->setCurrentCompany($user->current_company_id);
            }
        } else {
            // Si no tiene empresa actual, usar la primera disponible
            $this->companyContext->setCurrentCompany($companies[0]['id']);
            $user->current_company_id = $companies[0]['id'];
            $user->save();
        }

        Log::info('SetCompanyContext: FIN', [
            'user_id' => $user->id,
            'company_id' => $this->companyContext->getCurrentCompanyId(),
            'company_name' => $this->companyContext->getCurrentCompanyName(),
        ]);

        return $next($request);
    }
}
