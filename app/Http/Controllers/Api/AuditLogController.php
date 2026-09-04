<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\HasCompanyContext;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use HasCompanyContext, HandlesApiResponses;

    /**
     * GET /api/audit-logs
     * Listar logs de auditoría
     */
    public function index(Request $request)
    {
        $companyId = $this->getCurrentCompanyId();
        $user = auth()->user();

        $query = AuditLog::with(['user'])
            ->where('company_id', $companyId);

        // Filtros
        if ($request->action) {
            $query->where('action', $request->action);
        }

        if ($request->entity_type) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->entity_id) {
            $query->where('entity_id', $request->entity_id);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Super admin puede ver logs de todas las empresas
        if ($user->hasRole('super_admin') && $request->company_id) {
            $query->where('company_id', $request->company_id);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return $this->successResponse($logs);
    }

    /**
     * GET /api/audit-logs/{id}
     * Ver log específico
     */
    public function show(int $id)
    {
        $companyId = $this->getCurrentCompanyId();
        $user = auth()->user();

        $query = AuditLog::with(['user'])->where('id', $id);

        // Super admin puede ver cualquier log
        if (!$user->hasRole('super_admin')) {
            $query->where('company_id', $companyId);
        }

        $log = $query->first();

        if (!$log) {
            return $this->notFoundResponse('Log de auditoría no encontrado');
        }

        return $this->successResponse($log);
    }

    /**
     * GET /api/audit-logs/entity/{type}/{id}
     * Ver logs de una entidad específica
     */
    public function byEntity(string $type, int $id, Request $request)
    {
        $companyId = $this->getCurrentCompanyId();
        $user = auth()->user();

        $query = AuditLog::with(['user'])
            ->where('entity_type', $type)
            ->where('entity_id', $id);

        if (!$user->hasRole('super_admin')) {
            $query->where('company_id', $companyId);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return $this->successResponse($logs);
    }

    /**
     * GET /api/audit-logs/user/{id}
     * Ver logs de un usuario específico
     */
    public function byUser(int $id, Request $request)
    {
        $companyId = $this->getCurrentCompanyId();
        $user = auth()->user();

        $query = AuditLog::with(['user'])
            ->where('user_id', $id);

        if (!$user->hasRole('super_admin')) {
            $query->where('company_id', $companyId);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return $this->successResponse($logs);
    }

    /**
     * GET /api/audit-logs/stats
     * Estadísticas de auditoría
     */
    public function stats(Request $request)
    {
        $companyId = $this->getCurrentCompanyId();
        $user = auth()->user();

        $query = AuditLog::where('company_id', $companyId);

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $stats = [
            'total' => $query->count(),
            'by_action' => $query->selectRaw('action, count(*) as total')
                ->groupBy('action')
                ->get()
                ->pluck('total', 'action'),
            'by_entity_type' => $query->selectRaw('entity_type, count(*) as total')
                ->whereNotNull('entity_type')
                ->groupBy('entity_type')
                ->get()
                ->pluck('total', 'entity_type'),
            'by_user' => $query->selectRaw('user_id, count(*) as total')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->get()
                ->pluck('total', 'user_id'),
        ];

        return $this->successResponse($stats);
    }
}