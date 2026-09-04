<?php

namespace App\Http\Middleware;

use App\Helpers\PermissionHelper;
use App\Services\Context\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissions
{
    public function __construct(
        private CompanyContext $companyContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Super Admin tiene acceso completo
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Verificar permisos para la ruta actual
        $routeName = $request->route()?->getName();

        if ($routeName) {
            $parts = explode('.', $routeName);
            
            if (count($parts) >= 4) {
                $resource = $parts[2] ?? null;
                $action = $parts[3] ?? null;

                if ($resource && $action) {
                    $actionMap = [
                        'index' => 'view',
                        'create' => 'create',
                        'edit' => 'edit',
                        'update' => 'edit',
                        'destroy' => 'delete',
                        'show' => 'view',
                        'export' => 'export',
                        'import' => 'import',
                    ];

                    $permissionAction = $actionMap[$action] ?? $action;
                    $permissionName = $resource . '_' . $permissionAction;

                    if (!PermissionHelper::userCan($user, $permissionName)) {
                        abort(403, 'No tienes permiso para acceder a esta página.');
                    }
                }
            }
        }

        return $next($request);
    }
}