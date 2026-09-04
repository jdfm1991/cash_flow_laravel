<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use App\Traits\HandlesApiResponses;

class LogUserActivity
{
    use HandlesApiResponses;

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();

        // ✅ Verificar que la respuesta es JSON y exitosa
        if ($user && $request->method() === 'GET' && $this->isSuccessfulJsonResponse($response)) {
            $this->logViewActivity($request, $user);
        }

        return $response;
    }

    /**
     * Verificar si la respuesta es JSON exitosa
     */
    protected function isSuccessfulJsonResponse($response): bool
    {
        // ✅ Verificar que el método status existe y es 200
        if (!method_exists($response, 'status')) {
            return false;
        }

        // ✅ Verificar que el contenido es JSON
        if (!method_exists($response, 'headers')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');
        $isJson = str_contains($contentType, 'application/json');

        return $response->status() === 200 && $isJson;
    }

    private function logViewActivity(Request $request, $user): void
    {
        $entityId = $this->getEntityId($request);

        if ($entityId === null) {
            return;
        }

        AuditLog::create([
            'user_id' => $user->id,
            'company_id' => $user->current_company_id,
            'action' => 'view',
            'entity_type' => $this->getEntityType($request),
            'entity_id' => $entityId,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
            ],
        ]);
    }

    /**
     * Obtener entity_id de forma segura
     */
    private function getEntityId(Request $request): ?int
    {
        $id = $request->route('id');

        if ($id === null) {
            return null;
        }

        if (!is_numeric($id)) {
            return null;
        }

        return (int) $id;
    }

    private function getEntityType(Request $request): string
    {
        $path = $request->path();
        
        $mapping = [
            'companies' => 'Company',
            'transactions' => 'Transaction',
            'categories' => 'Category',
            'accounts' => 'Account',
            'bank-accounts' => 'BankAccount',
            'users' => 'User',
            'banks' => 'Bank',
            'currencies' => 'Currency',
            'exchange-rates' => 'ExchangeRate',
            'reports' => 'Report',
            'dashboard' => 'Dashboard',
            'profile' => 'Profile',
            'settings' => 'Setting',
        ];

        foreach ($mapping as $pathSegment => $entityType) {
            if (str_contains($path, $pathSegment)) {
                return $entityType;
            }
        }

        return 'Unknown';
    }
}