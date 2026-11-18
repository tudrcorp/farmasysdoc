<?php

namespace App\Filament\BusinessPartners\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Página del enlace del correo: «Cree su clave» la primera vez (invitación) o «Cambie su clave».
 * Al guardar, el usuario queda activado (listener de {@see PasswordReset}).
 */
class ResetPassword extends BaseResetPassword
{
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}

    protected function isResetPasswordRateLimited(?string $email): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->isFirstAccess() ? 'Cree su clave' : 'Cambie su clave';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->isFirstAccess()
            ? 'Es su primer ingreso a dale · Aliados. Elija una clave segura que solo usted conozca.'
            : 'Elija una clave segura que solo usted conozca.';
    }

    private function isFirstAccess(): bool
    {
        if (blank($this->email)) {
            return false;
        }

        return User::query()
            ->where('email', $this->email)
            ->whereNull('partner_activated_at')
            ->exists();
    }
}
