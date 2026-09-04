<?php

namespace App\Traits;

use App\Models\AuditLog;
use App\Enums\AuditAction;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Registrar actividad en el log de auditoría
     */
    public function logActivity(
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $note = null
    ): void {
        $user = auth()->user();

        AuditLog::create([
            'user_id' => $user?->id,
            'company_id' => $user?->current_company_id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'metadata' => [
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'url' => Request::fullUrl(),
                'method' => Request::method(),
                'note' => $note,
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * Registrar creación
     */
    public function logCreated(string $entityType, int $entityId, array $data, ?string $note = null): void
    {
        $this->logActivity(
            action: AuditAction::CREATE->value,
            entityType: $entityType,
            entityId: $entityId,
            newData: $data,
            note: $note
        );
    }

    /**
     * Registrar actualización
     */
    public function logUpdated(string $entityType, int $entityId, array $oldData, array $newData, ?string $note = null): void
    {
        $this->logActivity(
            action: AuditAction::UPDATE->value,
            entityType: $entityType,
            entityId: $entityId,
            oldData: $oldData,
            newData: $newData,
            note: $note
        );
    }

    /**
     * Registrar eliminación
     */
    public function logDeleted(string $entityType, int $entityId, array $oldData, ?string $note = null): void
    {
        $this->logActivity(
            action: AuditAction::DELETE->value,
            entityType: $entityType,
            entityId: $entityId,
            oldData: $oldData,
            note: $note
        );
    }

    /**
     * Registrar inicio de sesión
     */
    public function logLogin(int $userId, ?string $note = null): void
    {
        $this->logActivity(
            action: AuditAction::LOGIN->value,
            entityType: 'User',
            entityId: $userId,
            note: $note ?? 'Inicio de sesión'
        );
    }

    /**
     * Registrar cierre de sesión
     */
    public function logLogout(int $userId, ?string $note = null): void
    {
        $this->logActivity(
            action: AuditAction::LOGOUT->value,
            entityType: 'User',
            entityId: $userId,
            note: $note ?? 'Cierre de sesión'
        );
    }

    /**
     * Registrar cambio de empresa
     */
    public function logCompanySwitch(int $userId, int $fromCompanyId, int $toCompanyId): void
    {
        $this->logActivity(
            action: AuditAction::SWITCH_COMPANY->value,
            entityType: 'Company',
            entityId: $toCompanyId,
            oldData: ['company_id' => $fromCompanyId],
            newData: ['company_id' => $toCompanyId],
            note: 'Cambio de empresa'
        );
    }
}