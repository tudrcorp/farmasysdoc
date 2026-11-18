<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Activación (al crear su clave con el enlace del correo) y último ingreso de los usuarios
 * del panel de aliados. Se guarda sin disparar eventos para no llenar la auditoría.
 */
class TrackPartnerPanelAccess
{
    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->isPartnerCompanyUser()) {
            return;
        }

        $changes = [];

        if ($user->partner_activated_at === null) {
            $changes['partner_activated_at'] = now();
        }

        if ($user->email_verified_at === null) {
            $changes['email_verified_at'] = now();
        }

        if ($changes !== []) {
            $user->forceFill($changes)->saveQuietly();
        }
    }

    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->isPartnerCompanyUser()) {
            return;
        }

        $user->forceFill([
            'partner_last_login_at' => now(),
            'partner_activated_at' => $user->partner_activated_at ?? now(),
        ])->saveQuietly();
    }
}
