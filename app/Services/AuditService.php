<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Registrar una acción en el log de auditoría
     */
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?array $metadata = null
    ): ?AuditLog {
        try {
            $user = Auth::user();
            $companyId = $user?->current_company_id;

            // Si no hay company_id y la entidad lo tiene, usar el de la entidad
            if (!$companyId && $entity && isset($entity->company_id)) {
                $companyId = $entity->company_id;
            }

            return AuditLog::create([
                'user_id' => $user?->id,
                'company_id' => $companyId,
                'action' => $action,
                'entity_type' => $entity ? class_basename($entity) : null,
                'entity_id' => $entity?->id,
                'old_data' => $oldData,
                'new_data' => $newData,
                'metadata' => array_merge($this->getDefaultMetadata(), $metadata ?? []),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error registrando auditoría', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Registrar login
     */
    public function logLogin(int $userId): void
    {
        $this->log(
            action: AuditLog::ACTION_LOGIN,
            metadata: ['user_id' => $userId],
        );
    }

    /**
     * Registrar logout
     */
    public function logLogout(int $userId): void
    {
        $this->log(
            action: AuditLog::ACTION_LOGOUT,
            metadata: ['user_id' => $userId],
        );
    }

    /**
     * Registrar login fallido
     */
    public function logLoginFailed(string $email): void
    {
        $this->log(
            action: AuditLog::ACTION_LOGIN_FAILED,
            metadata: ['email' => $email],
        );
    }

    /**
     * Registrar creación
     */
    public function logCreated(Model $entity): void
    {
        $this->log(
            action: AuditLog::ACTION_CREATE,
            entity: $entity,
            newData: $this->sanitizeData($entity->toArray()),
        );
    }

    /**
     * Registrar actualización
     */
    public function logUpdated(Model $entity, array $oldData): void
    {
        $this->log(
            action: AuditLog::ACTION_UPDATE,
            entity: $entity,
            oldData: $this->sanitizeData($oldData),
            newData: $this->sanitizeData($entity->getChanges()),
        );
    }

    /**
     * Registrar eliminación
     */
    public function logDeleted(Model $entity): void
    {
        $this->log(
            action: AuditLog::ACTION_DELETE,
            entity: $entity,
            oldData: $this->sanitizeData($entity->toArray()),
        );
    }

    /**
     * Registrar restauración
     */
    public function logRestored(Model $entity): void
    {
        $this->log(
            action: AuditLog::ACTION_RESTORE,
            entity: $entity,
            newData: $this->sanitizeData($entity->toArray()),
        );
    }

    /**
     * Registrar exportación
     */
    public function logExport(string $reportName, ?int $companyId = null): void
    {
        $this->log(
            action: AuditLog::ACTION_EXPORT,
            metadata: [
                'report' => $reportName,
                'company_id' => $companyId ?? Auth::user()?->current_company_id,
            ],
        );
    }

    /**
     * Registrar importación
     */
    public function logImport(string $importType, array $stats = []): void
    {
        $this->log(
            action: AuditLog::ACTION_IMPORT,
            metadata: [
                'import_type' => $importType,
                'stats' => $stats,
            ],
        );
    }

    /**
     * Obtener metadata por defecto (IP, user_agent, etc.)
     */
    protected function getDefaultMetadata(): array
    {
        return [
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
        ];
    }

    /**
     * Sanitizar datos (remover campos sensibles)
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'remember_token',
            'api_token',
            'token',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        return $data;
    }
}