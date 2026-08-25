<?php

namespace App\Services\BdvConciliation;

use App\Models\User;
use App\Support\BdvConciliation\NotifyManualBdvConciliationOtp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ManualBdvConciliationOtpService
{
    public const TTL_SECONDS = 600;

    public function __construct(
        private readonly NotifyManualBdvConciliationOtp $notifier,
    ) {}

    /**
     * Genera un OTP de 6 dígitos, lo guarda hasheado (TTL 10 min) y lo envía por email y WhatsApp.
     *
     * @param  array{
     *     branch_name?: string|null,
     *     reference?: string|null,
     *     amount?: string|null,
     *     payer_document?: string|null,
     *     payer_phone?: string|null,
     *     destination_phone?: string|null,
     *     payment_date?: string|null,
     *     origin_bank?: string|null
     * }  $context
     */
    public function issue(User $actor, array $context = []): string
    {
        if (! $this->actorCanRegister($actor)) {
            throw ValidationException::withMessages([
                'otp_code' => 'Solo gerentes y administradores pueden solicitar un OTP de conciliación manual.',
            ]);
        }

        $userId = (int) $actor->getKey();
        $code = $this->generateUnusedCode($userId);

        Cache::put(
            $this->cacheKey($userId),
            Hash::make($code),
            self::TTL_SECONDS,
        );

        $this->notifier->notify(
            actor: $actor,
            otpCode: $code,
            context: $context,
            ttlSeconds: self::TTL_SECONDS,
        );

        return $code;
    }

    /**
     * OTP solicitado por el cajero en caja: la clave se envía al gerente de la sucursal y a administradores.
     *
     * @param  array{
     *     branch_name?: string|null,
     *     reference?: string|null,
     *     amount?: string|null,
     *     payer_document?: string|null,
     *     payer_phone?: string|null,
     *     destination_phone?: string|null,
     *     payment_date?: string|null,
     *     origin_bank?: string|null
     * }  $context
     */
    public function issueForPosCashier(User $cashier, int $branchId, array $context = []): string
    {
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'otp_code' => 'No se pudo determinar la sucursal del cajero para enviar el OTP.',
            ]);
        }

        if ($this->notifier->contactablePosRecipients($branchId) === []) {
            throw ValidationException::withMessages([
                'otp_code' => 'No hay gerente de la sucursal ni administradores con correo o WhatsApp para enviar la clave OTP.',
            ]);
        }

        $userId = (int) $cashier->getKey();
        $code = $this->generateUnusedCode($userId);

        Cache::put(
            $this->cacheKey($userId),
            Hash::make($code),
            self::TTL_SECONDS,
        );

        $this->notifier->notifyForPos(
            cashier: $cashier,
            branchId: $branchId,
            otpCode: $code,
            context: $context,
            ttlSeconds: self::TTL_SECONDS,
        );

        return $code;
    }

    public function verifyAndConsume(User $actor, ?string $code): void
    {
        $normalized = preg_replace('/\D/', '', (string) $code) ?? '';

        if (strlen($normalized) !== 6) {
            throw ValidationException::withMessages([
                'otp_code' => 'Ingrese el código OTP de 6 dígitos enviado por email y WhatsApp.',
            ]);
        }

        $userId = (int) $actor->getKey();

        if ($this->wasRecentlyConsumed($userId, $normalized)) {
            throw ValidationException::withMessages([
                'otp_code' => 'Esta clave OTP ya fue utilizada. Solicite una nueva para registrar otra conciliación.',
            ]);
        }

        $hash = Cache::get($this->cacheKey($userId));

        if (! is_string($hash) || $hash === '') {
            throw ValidationException::withMessages([
                'otp_code' => 'El código OTP expiró o no fue solicitado. Solicite uno nuevo (válido 10 minutos).',
            ]);
        }

        if (! Hash::check($normalized, $hash)) {
            throw ValidationException::withMessages([
                'otp_code' => 'El código OTP es incorrecto.',
            ]);
        }

        $this->invalidate($actor);
        $this->markConsumed($userId, $normalized);
    }

    public function invalidate(User $actor): void
    {
        Cache::forget($this->cacheKey((int) $actor->getKey()));
    }

    public function actorCanRegister(?User $actor): bool
    {
        return $actor instanceof User
            && ($actor->isAdministrator() || $actor->isManager());
    }

    private function generateUnusedCode(int $userId): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

            if (! $this->wasRecentlyConsumed($userId, $code)) {
                return $code;
            }
        }

        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    private function markConsumed(int $userId, string $code): void
    {
        Cache::put($this->usedCacheKey($userId, $code), true, self::TTL_SECONDS);
    }

    private function wasRecentlyConsumed(int $userId, string $code): bool
    {
        return Cache::has($this->usedCacheKey($userId, $code));
    }

    private function cacheKey(int $userId): string
    {
        return 'bdv_manual_conciliation.otp.'.$userId;
    }

    private function usedCacheKey(int $userId, string $code): string
    {
        return 'bdv_manual_conciliation.otp.used.'.$userId.'.'.$code;
    }
}
