<?php

namespace App\Filament\BusinessPartners\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso a dale · Aliados. El límite de intentos es por correo + IP (no solo por IP), porque
 * los equipos de un aliado suelen salir a internet por la misma IP.
 */
class Login extends BaseLogin
{
    public const MAX_ATTEMPTS_PER_ACCOUNT = 5;

    public const MAX_ATTEMPTS_PER_IP = 60;

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $email = Str::lower(trim((string) ($data['email'] ?? '')));
        $accountKey = 'partner-login:'.sha1($email.'|'.request()->ip());
        $ipKey = 'partner-login-ip:'.sha1((string) request()->ip());

        foreach ([[$accountKey, self::MAX_ATTEMPTS_PER_ACCOUNT], [$ipKey, self::MAX_ATTEMPTS_PER_IP]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);

                throw ValidationException::withMessages([
                    'data.email' => "Demasiados intentos. Intente de nuevo en {$seconds} segundos.",
                ]);
            }
        }

        $this->assertPartnerCanEnter($email, (string) ($data['password'] ?? ''));

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            RateLimiter::hit($accountKey, 60);
            RateLimiter::hit($ipKey, 60);

            throw $exception;
        }

        RateLimiter::clear($accountKey);

        return $response;
    }

    /**
     * Con la clave correcta, explica por qué no puede entrar (suspendido, empresa inactiva o
     * personal de Farmadoc) en lugar del genérico «credenciales incorrectas».
     */
    private function assertPartnerCanEnter(string $email, string $password): void
    {
        if ($email === '') {
            return;
        }

        $user = User::query()->with('partnerCompany')->where('email', $email)->first();

        if ($user instanceof User && $user->canAccessPartnerPanel() && $user->partner_activated_at === null) {
            throw ValidationException::withMessages([
                'data.email' => 'Su cuenta todavía no tiene clave. Ábrala con el enlace «Crear mi clave» que le enviamos por correo, o pida uno nuevo en «¿Ha olvidado su contraseña?».',
            ]);
        }

        $reason = $user?->partnerPanelAccessDeniedReason();

        if ($reason !== null && $password !== '' && Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages(['data.email' => $reason]);
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication) ? parent::getHeading() : 'Ingrese a su cuenta';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication) ? parent::getSubheading() : 'Panel de aliados comerciales';
    }
}
