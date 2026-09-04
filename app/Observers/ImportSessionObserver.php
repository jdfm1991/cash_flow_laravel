<?php

namespace App\Observers;

use App\Models\ImportSession;

class ImportSessionObserver
{
    public function creating(ImportSession $session): void
    {
        $user = filament()->auth()->user();
        if ($user) {
            $session->user_id = $user->id;
        }
    }
}