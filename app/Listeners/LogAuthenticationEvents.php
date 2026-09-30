<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;

class LogAuthenticationEvents
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Registrar login exitoso
     */
    public function handleLogin(Login $event): void
    {
        $this->auditService->logLogin($event->user->id);
    }

    /**
     * Registrar logout
     */
    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            $this->auditService->logLogout($event->user->id);
        }
    }

    /**
     * Registrar login fallido
     */
    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'desconocido';
        $this->auditService->logLoginFailed($email);
    }
}