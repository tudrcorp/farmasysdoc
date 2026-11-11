<?php

namespace App\Services\Otp;

use App\Enums\OtpCodePurpose;
use App\Enums\OtpCodeStatus;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Claves OTP persistidas en `otp_codes`: 6 dígitos, válidas 10 minutos y de un solo uso.
 *
 * No se envían por correo ni WhatsApp: gerentes y administradores las consultan en «Claves OTP»
 * ({@see OtpCode::scopeVisibleTo()}). Cada usuario solo tiene una clave vigente por módulo:
 * pedir otra anula la anterior. Al usarse queda registrado quién la usó y en qué parte del sistema.
 */
final class OtpCodeService
{
    private const OUTCOME_MISSING = 'missing';

    private const OUTCOME_ALREADY_USED = 'already_used';

    private const OUTCOME_EXPIRED = 'expired';

    private const OUTCOME_WRONG = 'wrong';

    private const OUTCOME_LOCKED = 'locked';

    private const OUTCOME_FINGERPRINT = 'fingerprint';

    /**
     * @param  list<string>  $details
     */
    public function issue(
        OtpCodePurpose $purpose,
        User $requester,
        ?int $branchId,
        ?string $summary,
        array $details = [],
        ?string $fingerprint = null,
    ): OtpCode {
        return DB::transaction(function () use ($purpose, $requester, $branchId, $summary, $details, $fingerprint): OtpCode {
            $this->cancelActive($purpose, $requester, 'Reemplazada por una nueva solicitud.');

            return OtpCode::query()->create([
                'purpose' => $purpose,
                'status' => OtpCodeStatus::Pending,
                'code' => str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT),
                'fingerprint' => $fingerprint,
                'requested_by_user_id' => $requester->getKey(),
                'branch_id' => ($branchId !== null && $branchId > 0) ? $branchId : null,
                'summary' => filled($summary) ? mb_substr((string) $summary, 0, 500) : null,
                'details' => array_values(array_filter($details, fn (mixed $line): bool => is_string($line) && filled($line))),
                'failed_attempts' => 0,
                'expires_at' => now()->addSeconds(OtpCode::TTL_SECONDS),
            ]);
        });
    }

    /**
     * Verifica la clave sin consumirla (aviso temprano antes de una operación larga).
     */
    public function assertValid(
        OtpCodePurpose $purpose,
        User $user,
        ?string $code,
        ?string $fingerprint = null,
        string $errorKey = 'otp_code',
    ): OtpCode {
        return $this->check($purpose, $user, $code, $fingerprint, $errorKey, usedIn: null);
    }

    /**
     * Verifica la clave y la anula, registrando quién la usó y dónde.
     */
    public function consume(
        OtpCodePurpose $purpose,
        User $user,
        ?string $code,
        string $usedIn,
        ?string $fingerprint = null,
        string $errorKey = 'otp_code',
    ): OtpCode {
        return $this->check($purpose, $user, $code, $fingerprint, $errorKey, usedIn: $usedIn);
    }

    public function cancelActive(OtpCodePurpose $purpose, User $requester, string $reason): void
    {
        OtpCode::query()
            ->where('purpose', $purpose->value)
            ->where('requested_by_user_id', $requester->getKey())
            ->where('status', OtpCodeStatus::Pending->value)
            ->update([
                'status' => OtpCodeStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    private function check(
        OtpCodePurpose $purpose,
        User $user,
        ?string $code,
        ?string $fingerprint,
        string $errorKey,
        ?string $usedIn,
    ): OtpCode {
        $normalized = preg_replace('/\D/', '', (string) $code) ?? '';

        if (strlen($normalized) !== 6) {
            throw ValidationException::withMessages([
                $errorKey => 'Ingrese el código OTP de 6 dígitos. Pídalo al gerente de la sucursal o a un administrador.',
            ]);
        }

        $outcome = DB::transaction(function () use ($purpose, $user, $normalized, $fingerprint, $usedIn): OtpCode|string {
            $otp = OtpCode::query()
                ->where('purpose', $purpose->value)
                ->where('requested_by_user_id', $user->getKey())
                ->where('status', OtpCodeStatus::Pending->value)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp instanceof OtpCode) {
                return $this->wasRecentlyUsed($purpose, $user, $normalized)
                    ? self::OUTCOME_ALREADY_USED
                    : self::OUTCOME_MISSING;
            }

            if ($otp->isExpired()) {
                $otp->update(['status' => OtpCodeStatus::Expired]);

                return self::OUTCOME_EXPIRED;
            }

            if (! hash_equals((string) $otp->code, $normalized)) {
                $failedAttempts = $otp->failed_attempts + 1;

                if ($failedAttempts >= OtpCode::MAX_FAILED_ATTEMPTS) {
                    $otp->update([
                        'failed_attempts' => $failedAttempts,
                        'status' => OtpCodeStatus::Cancelled,
                        'cancelled_at' => now(),
                        'cancellation_reason' => 'Demasiados intentos con un código incorrecto.',
                    ]);

                    return self::OUTCOME_LOCKED;
                }

                $otp->update(['failed_attempts' => $failedAttempts]);

                return self::OUTCOME_WRONG;
            }

            if ($fingerprint !== null && ! hash_equals((string) $otp->fingerprint, $fingerprint)) {
                return self::OUTCOME_FINGERPRINT;
            }

            if ($usedIn !== null) {
                $otp->update([
                    'status' => OtpCodeStatus::Used,
                    'used_by_user_id' => $user->getKey(),
                    'used_at' => now(),
                    'used_in' => mb_substr($usedIn, 0, 255),
                    'used_url' => $this->currentUrl(),
                ]);
            }

            return $otp;
        });

        if ($outcome instanceof OtpCode) {
            return $outcome;
        }

        throw ValidationException::withMessages([
            $errorKey => match ($outcome) {
                self::OUTCOME_ALREADY_USED => 'Esta clave OTP ya fue utilizada. Solicite una nueva.',
                self::OUTCOME_EXPIRED => 'El código OTP venció. Solicite uno nuevo (válido 10 minutos).',
                self::OUTCOME_WRONG => 'El código OTP es incorrecto.',
                self::OUTCOME_LOCKED => 'Demasiados intentos con un código incorrecto. Solicite una clave OTP nueva.',
                self::OUTCOME_FINGERPRINT => 'Los datos cambiaron desde que se solicitó la clave OTP. Solicite una nueva.',
                default => 'El código OTP no fue solicitado o ya no está vigente. Solicite uno nuevo (válido 10 minutos).',
            },
        ]);
    }

    private function wasRecentlyUsed(OtpCodePurpose $purpose, User $user, string $code): bool
    {
        return OtpCode::query()
            ->where('purpose', $purpose->value)
            ->where('requested_by_user_id', $user->getKey())
            ->where('status', OtpCodeStatus::Used->value)
            ->where('used_at', '>=', now()->subSeconds(OtpCode::TTL_SECONDS))
            ->get(['id', 'code'])
            ->contains(fn (OtpCode $used): bool => hash_equals((string) $used->code, $code));
    }

    /**
     * URL de la pantalla donde se usó la clave; en peticiones Livewire es la página que la contiene.
     */
    private function currentUrl(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();
        $url = $request->headers->has('X-Livewire')
            ? (string) $request->headers->get('referer', '')
            : $request->fullUrl();

        return $url !== '' ? mb_substr($url, 0, 500) : null;
    }
}
