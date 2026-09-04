<?php

namespace App\Observers;

use App\Models\BankAccount;
use App\Models\AuditLog;
use App\Enums\AuditAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BankAccountObserver
{
    /**
     * Handle the BankAccount "created" event.
     */
    public function created(BankAccount $bankAccount): void
    {
        // Registrar auditoría
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $bankAccount->company_id,
            'action' => AuditAction::CREATE->value,
            'entity_type' => 'BankAccount',
            'entity_id' => $bankAccount->id,
            'new_data' => $bankAccount->toArray(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Cuenta bancaria creada", [
            'account_id' => $bankAccount->id,
            'alias' => $bankAccount->alias,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the BankAccount "updated" event.
     */
    public function updated(BankAccount $bankAccount): void
    {
        // Invalidar caché de saldo
        Cache::forget("balance:account:{$bankAccount->id}");

        // Registrar auditoría
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $bankAccount->company_id,
            'action' => AuditAction::UPDATE->value,
            'entity_type' => 'BankAccount',
            'entity_id' => $bankAccount->id,
            'old_data' => $bankAccount->getOriginal(),
            'new_data' => $bankAccount->getChanges(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Cuenta bancaria actualizada", [
            'account_id' => $bankAccount->id,
            'changes' => $bankAccount->getChanges(),
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the BankAccount "deleted" event.
     */
    public function deleted(BankAccount $bankAccount): void
    {
        // Invalidar caché de saldo
        Cache::forget("balance:account:{$bankAccount->id}");

        // Registrar auditoría
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $bankAccount->company_id,
            'action' => AuditAction::DELETE->value,
            'entity_type' => 'BankAccount',
            'entity_id' => $bankAccount->id,
            'old_data' => $bankAccount->toArray(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Cuenta bancaria eliminada", [
            'account_id' => $bankAccount->id,
            'user_id' => auth()->id(),
        ]);
    }
}