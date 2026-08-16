<?php

namespace App\Services\Hr;

use App\Mail\EmployeePortalOtpMail;
use App\Models\Employee;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class EmployeePortalPasswordReset
{
    public const CHANNEL_PHONE = 'phone';

    public const CHANNEL_EMAIL = 'email';

    public const TTL_SECONDS = 600;

    public function __construct(
        private UltramsgWhatsAppClient $whatsApp,
        private EmployeePortalAuthenticator $authenticator,
    ) {}

    public function issue(Employee $employee, string $channel, string $ip): void
    {
        $this->ensureSendNotRateLimited($employee, $ip);

        $channel = $this->assertChannel($employee, $channel);
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->otpKey($employee), Hash::make($code), self::TTL_SECONDS);
        Cache::forget($this->verifiedKey($employee));

        if ($channel === self::CHANNEL_EMAIL) {
            $this->sendEmail($employee, $code);
        } else {
            $this->sendWhatsApp($employee, $code);
        }

        RateLimiter::hit($this->sendLimitKey($employee, $ip), 900);
    }

    public function verify(Employee $employee, string $code, string $ip): void
    {
        $this->ensureVerifyNotRateLimited($employee, $ip);

        $normalized = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($normalized) !== 6) {
            RateLimiter::hit($this->verifyLimitKey($employee, $ip), 900);

            throw ValidationException::withMessages([
                'otpCode' => 'Escribe el código de 6 dígitos.',
            ]);
        }

        $hash = Cache::get($this->otpKey($employee));

        if (! is_string($hash) || $hash === '') {
            RateLimiter::hit($this->verifyLimitKey($employee, $ip), 900);

            throw ValidationException::withMessages([
                'otpCode' => 'El código expiró o no fue solicitado. Pide uno nuevo.',
            ]);
        }

        if (! Hash::check($normalized, $hash)) {
            RateLimiter::hit($this->verifyLimitKey($employee, $ip), 900);

            throw ValidationException::withMessages([
                'otpCode' => 'El código no es correcto.',
            ]);
        }

        Cache::forget($this->otpKey($employee));
        Cache::put($this->verifiedKey($employee), true, self::TTL_SECONDS);
        RateLimiter::clear($this->verifyLimitKey($employee, $ip));
    }

    public function resetPassword(Employee $employee, string $password): void
    {
        if (! Cache::get($this->verifiedKey($employee))) {
            throw ValidationException::withMessages([
                'password' => 'Primero debes validar el código que te enviamos.',
            ]);
        }

        $this->authenticator->setPassword($employee, $password);
        Cache::forget($this->verifiedKey($employee));
        Cache::forget($this->otpKey($employee));
    }

    public function canSendToPhone(Employee $employee): bool
    {
        return WhatsAppLink::normalizePhoneDigits($employee->phone) !== null;
    }

    public function canSendToEmail(Employee $employee): bool
    {
        return filled($employee->email) && filter_var($employee->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function assertChannel(Employee $employee, string $channel): string
    {
        if ($channel === self::CHANNEL_EMAIL && $this->canSendToEmail($employee)) {
            return self::CHANNEL_EMAIL;
        }

        if ($channel === self::CHANNEL_PHONE && $this->canSendToPhone($employee)) {
            return self::CHANNEL_PHONE;
        }

        throw ValidationException::withMessages([
            'otpChannel' => 'Elige un teléfono o un correo válido de tu expediente.',
        ]);
    }

    private function sendEmail(Employee $employee, string $code): void
    {
        try {
            Mail::to((string) $employee->email)->send(new EmployeePortalOtpMail(
                $employee,
                $code,
                (int) (self::TTL_SECONDS / 60),
            ));
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'otpChannel' => 'No pudimos enviar el código al correo. Inténtalo de nuevo.',
            ]);
        }
    }

    private function sendWhatsApp(Employee $employee, string $code): void
    {
        $digits = WhatsAppLink::normalizePhoneDigits($employee->phone);

        if ($digits === null) {
            throw ValidationException::withMessages([
                'otpChannel' => 'No hay un teléfono válido en tu expediente.',
            ]);
        }

        if (! $this->whatsApp->isEnabled()) {
            throw ValidationException::withMessages([
                'otpChannel' => 'Ahora no podemos enviar el código al teléfono. Prueba el correo.',
            ]);
        }

        $body = "Farmadoc Portal\nTu código para restablecer la clave es: {$code}\nVálido por ".(int) (self::TTL_SECONDS / 60).' minutos. No lo compartas.';

        if (! $this->whatsApp->sendTextMessage($digits, $body)) {
            throw ValidationException::withMessages([
                'otpChannel' => 'No pudimos enviar el código al teléfono. Inténtalo de nuevo o usa el correo.',
            ]);
        }
    }

    private function ensureSendNotRateLimited(Employee $employee, string $ip): void
    {
        if (! RateLimiter::tooManyAttempts($this->sendLimitKey($employee, $ip), 4)) {
            return;
        }

        throw ValidationException::withMessages([
            'otpChannel' => 'Demasiados envíos. Espera unos minutos e inténtalo de nuevo.',
        ]);
    }

    private function ensureVerifyNotRateLimited(Employee $employee, string $ip): void
    {
        if (! RateLimiter::tooManyAttempts($this->verifyLimitKey($employee, $ip), 8)) {
            return;
        }

        throw ValidationException::withMessages([
            'otpCode' => 'Demasiados intentos. Pide un código nuevo más tarde.',
        ]);
    }

    private function otpKey(Employee $employee): string
    {
        return 'employee-portal.reset.otp.'.$employee->getKey();
    }

    private function verifiedKey(Employee $employee): string
    {
        return 'employee-portal.reset.verified.'.$employee->getKey();
    }

    private function sendLimitKey(Employee $employee, string $ip): string
    {
        return 'employee-portal.reset.send.'.$employee->getKey().'.'.$ip;
    }

    private function verifyLimitKey(Employee $employee, string $ip): string
    {
        return 'employee-portal.reset.verify.'.$employee->getKey().'.'.$ip;
    }
}
