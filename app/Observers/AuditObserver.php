<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    /**
     * Modelos que NO deben auditarse
     */
    protected array $excludedModels = [
        AuditLog::class,
        \App\Models\ImportedTransaction::class,
    ];

    /**
     * Campos que NO deben auditarse (comparación)
     */
    protected array $excludedFields = [
        'updated_at',
        'created_at',
    ];

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Al crear un registro
     */
    public function created(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $this->auditService->logCreated($model);
    }

    /**
     * Al actualizar un registro
     */
    public function updated(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $changes = $model->getChanges();

        // Si no hay cambios reales, no registrar
        if (empty($changes) || (count($changes) === 1 && isset($changes['updated_at']))) {
            return;
        }

        $oldData = [];
        foreach ($changes as $field => $newValue) {
            if (in_array($field, $this->excludedFields)) {
                continue;
            }
            $oldData[$field] = $model->getOriginal($field);
        }

        $this->auditService->logUpdated($model, $oldData);
    }

    /**
     * Al eliminar un registro
     */
    public function deleted(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $this->auditService->logDeleted($model);
    }

    /**
     * Al restaurar un registro
     */
    public function restored(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $this->auditService->logRestored($model);
    }

    /**
     * Verificar si se debe omitir el registro
     */
    protected function shouldSkip(Model $model): bool
    {
        // No auditar si no hay usuario autenticado
        if (!auth()->check()) {
            return true;
        }

        // No auditar modelos excluidos
        if (in_array(get_class($model), $this->excludedModels)) {
            return true;
        }

        return false;
    }
}