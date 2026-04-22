<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

final class RecordAuthenticationAudit
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        AuditLogger::forAuthentication(
            'login',
            $user,
            'Inicio de sesión',
            ['guard' => $event->guard],
            $this->request(),
        );
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        AuditLogger::forAuthentication(
            'logout',
            $user,
            'Cierre de sesión',
            ['guard' => $event->guard],
            $this->request(),
        );
    }

    public function handleFailed(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null)
            ? (string) $event->credentials['email']
            : null;

        AuditLogger::record(
            event: 'login_failed',
            description: 'Intento de inicio de sesión fallido',
            properties: [
                'email' => $email,
                'guard' => $event->guard,
            ],
            request: $this->request(),
            user: null,
        );
    }

    private function request(): ?Request
    {
        $r = request();

        return $r instanceof Request ? $r : null;
    }
}
