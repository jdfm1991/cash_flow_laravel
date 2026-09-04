<?php

namespace App\Observers;

use App\Models\User;
use App\Models\AuditLog;
use App\Enums\AuditAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $user->current_company_id,
            'action' => AuditAction::CREATE->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'new_data' => $user->toArray(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Usuario creado", [
            'user_id' => $user->id,
            'email' => $user->email,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Invalidar caché del usuario
        Cache::forget("user:{$user->id}");

        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $user->current_company_id,
            'action' => AuditAction::UPDATE->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_data' => $user->getOriginal(),
            'new_data' => $user->getChanges(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Usuario actualizado", [
            'user_id' => $user->id,
            'changes' => $user->getChanges(),
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        Cache::forget("user:{$user->id}");

        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $user->current_company_id,
            'action' => AuditAction::DELETE->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_data' => $user->toArray(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        Log::info("Usuario eliminado", [
            'user_id' => $user->id,
            'deleted_by' => auth()->id(),
        ]);
    }
}