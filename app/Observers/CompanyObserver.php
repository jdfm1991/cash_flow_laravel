<?php

namespace App\Observers;

use App\Models\Company;
use App\Models\AuditLog;
use App\Enums\AuditAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     */
    public function created(Company $company): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $company->id,
            'action' => AuditAction::CREATE->value,
            'entity_type' => 'Company',
            'entity_id' => $company->id,
            'new_data' => $company->toArray(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Empresa creada", [
            'company_id' => $company->id,
            'name' => $company->name,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Handle the Company "updated" event.
     */
    public function updated(Company $company): void
    {
        Cache::forget("company:{$company->id}");

        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $company->id,
            'action' => AuditAction::UPDATE->value,
            'entity_type' => 'Company',
            'entity_id' => $company->id,
            'old_data' => $company->getOriginal(),
            'new_data' => $company->getChanges(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Empresa actualizada", [
            'company_id' => $company->id,
            'changes' => $company->getChanges(),
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Handle the Company "deleted" event.
     */
    public function deleted(Company $company): void
    {
        Cache::forget("company:{$company->id}");

        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $company->id,
            'action' => AuditAction::DELETE->value,
            'entity_type' => 'Company',
            'entity_id' => $company->id,
            'old_data' => $company->toArray(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Empresa eliminada", [
            'company_id' => $company->id,
            'deleted_by' => auth()->id(),
        ]);
    }
}