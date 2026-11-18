<?php

namespace App\Filament\BusinessPartners\Pages\Auth;

use App\Jobs\SendPartnerPanelAccessJob;
use App\Models\User;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * «¿Olvidó su clave?» de dale · Aliados: envía el correo con la imagen de dale y responde
 * siempre lo mismo, exista o no el correo (no revela quién tiene cuenta).
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public const MAX_REQUESTS_PER_ACCOUNT = 3;

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}

    public function request(): void
    {
        $data = $this->form->getState();
        $email = Str::lower(trim((string) ($data['email'] ?? '')));
        $key = 'partner-reset:'.sha1($email);

        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS_PER_ACCOUNT)) {
            Notification::make()
                ->title('Ya pidió varios enlaces')
                ->body('Revise su correo (también la carpeta de spam) o intente de nuevo en unos minutos.')
                ->warning()
                ->send();

            return;
        }

        RateLimiter::hit($key, 600);

        $user = User::query()->with('partnerCompany')->where('email', $email)->first();

        if ($user instanceof User && $user->canAccessPartnerPanel()) {
            SendPartnerPanelAccessJob::dispatch((int) $user->getKey(), $user->partner_activated_at === null);
        }

        Notification::make()
            ->title('Revise su correo')
            ->body('Si el correo pertenece a un usuario activo, recibirá en unos minutos un enlace para crear su clave.')
            ->success()
            ->send();

        $this->form->fill();
    }

    public function getHeading(): string|Htmlable|null
    {
        return '¿Olvidó su clave?';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Le enviaremos un enlace a su correo para crear una nueva.';
    }
}
