<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCompanyContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        // Verificar si el usuario tiene empresas asignadas
        $companies = $user->companies;

        if ($companies->isEmpty()) {
            return response()->json([
                'message' => 'Usuario no tiene empresas asignadas',
            ], 403);
        }

        // Si no tiene empresa activa, pero solo tiene una, asignarla automáticamente
        if (!$user->current_company_id && $companies->count() === 1) {
            $user->update(['current_company_id' => $companies->first()->id]);
            $user->refresh();
        }

        // Verificar que el usuario tenga una empresa activa
        if (!$user->current_company_id) {
            return response()->json([
                'message' => 'Debes seleccionar una empresa para continuar',
                'companies' => $companies->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'logo' => $company->logo_url,
                    ];
                }),
            ], 400);
        }

        // Verificar que la empresa activa exista y esté activa
        $company = $companies->firstWhere('id', $user->current_company_id);

        if (!$company || !$company->is_active) {
            return response()->json([
                'message' => 'La empresa seleccionada no está disponible',
            ], 403);
        }

        // Guardar la empresa en el contexto de la aplicación
        app()->instance('current_company', $company);

        return $next($request);
    }
}