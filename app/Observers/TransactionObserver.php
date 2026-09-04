<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\AuditLog;
use App\Enums\AuditAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // 1. Invalidar caché de saldo de la cuenta bancaria
        if ($transaction->bank_account_id) {
            Cache::forget("balance:account:{$transaction->bank_account_id}");
        }

        // 2. Registrar auditoría
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $transaction->company_id,
            'action' => AuditAction::CREATE->value,
            'entity_type' => 'Transaction',
            'entity_id' => $transaction->id,
            'new_data' => $transaction->toArray(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        // 3. Log
        Log::info("Transacción creada", [
            'transaction_id' => $transaction->id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        // 1. Invalidar caché de saldo
        if ($transaction->bank_account_id) {
            Cache::forget("balance:account:{$transaction->bank_account_id}");
        }

        // 2. Si cambió la cuenta bancaria, invalidar la anterior también
        if ($transaction->wasChanged('bank_account_id')) {
            $oldBankAccountId = $transaction->getOriginal('bank_account_id');
            if ($oldBankAccountId) {
                Cache::forget("balance:account:{$oldBankAccountId}");
            }
        }

        // 3. Registrar auditoría
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $transaction->company_id,
            'action' => AuditAction::UPDATE->value,
            'entity_type' => 'Transaction',
            'entity_id' => $transaction->id,
            'old_data' => $transaction->getOriginal(),
            'new_data' => $transaction->getChanges(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        // 4. Log
        Log::info("Transacción actualizada", [
            'transaction_id' => $transaction->id,
            'changes' => $transaction->getChanges(),
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        // 1. Invalidar caché de saldo
        if ($transaction->bank_account_id) {
            Cache::forget("balance:account:{$transaction->bank_account_id}");
        }

        // 2. Registrar auditoría
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $transaction->company_id,
            'action' => AuditAction::DELETE->value,
            'entity_type' => 'Transaction',
            'entity_id' => $transaction->id,
            'old_data' => $transaction->toArray(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        // 3. Log
        Log::info("Transacción eliminada", [
            'transaction_id' => $transaction->id,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        // 1. Invalidar caché de saldo
        if ($transaction->bank_account_id) {
            Cache::forget("balance:account:{$transaction->bank_account_id}");
        }

        // 2. Log
        Log::info("Transacción restaurada", [
            'transaction_id' => $transaction->id,
            'user_id' => auth()->id(),
        ]);
    }
}