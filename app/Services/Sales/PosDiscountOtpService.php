<?php

namespace App\Services\Sales;

use App\Models\User;
use App\Support\Sales\NotifyPosDiscountOtp;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * OTP para registrar en caja una venta con descuento manual: 6 dígitos, 5 minutos, un solo uso,
 * atada a la huella del descuento (PosDiscountOtpRequirement) y enviada al gerente de la sucursal y a los administradores.
 */
final class PosDiscountOtpService
{
    public const TTL_SECONDS = 300;

    public const MAX_ATTEMPTS = 5;

    public const ERROR_KEY = 'pos_discount_otp_code';

    public function __construct(
        private readonly NotifyPosDiscountOtp $notifier,
    ) {}

    /**
     * @param  array{
     *     branch_name?: string|null,
     *     client_name?: string|null,
     *     sale_percent?: string|null,
     *     lines?: list<string>,
     *     discount_amount?: string|null,
     *     total?: string|null
     * }  $context
     */
    public function issue(User $cashier, int $branchId, string $fingerprint, array $context = []): void
    {
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'No se pudo determinar la sucursal del cajero para enviar el OTP.',
            ]);
        }

        if ($this->notifier->contactableRecipients($branchId) === []) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'No hay gerente de la sucursal ni administradores con correo o WhatsApp para enviar la clave OTP.',
            ]);
        }

        $userId = (int) $cashier->getKey();
        $code = $this->generateUnusedCode($userId);

        $this->store($userId, [
            'hash' => Hash::make($code),
            'fingerprint' => $fingerprint,
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]);

        $this->notifier->notify(
            cashier: $cashier,
            branchId: $branchId,
            otpCode: $code,
            context: $context,
            ttlSeconds: self::TTL_SECONDS,
        );
    }

    /**
     * Verifica sin consumir (aviso temprano en caja). La OTP se consume con verifyAndConsume() justo antes de crear la venta.
     */
    public function assertValid(User $cashier, ?string $code, string $fingerprint): void
    {
        $this->withLock($cashier, fn () => $this->check($cashier, $code, $fingerprint, consume: false));
    }

    public function verifyAndConsume(User $cashier, ?string $code, string $fingerprint): void
    {
        $this->withLock($cashier, fn () => $this->check($cashier, $code, $fingerprint, consume: true));
    }

    private function check(User $cashier, ?string $code, string $fingerprint, bool $consume): void
    {
        $normalized = preg_replace('/\D/', '', (string) $code) ?? '';

        if (strlen($normalized) !== 6) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'Esta venta tiene descuento: ingrese el código OTP de 6 dígitos que recibió el gerente.',
            ]);
        }

        $userId = (int) $cashier->getKey();

        if (Cache::has($this->usedCacheKey($userId, $normalized))) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'Esta clave OTP ya fue utilizada. Solicite una nueva.',
            ]);
        }

        $entry = Cache::get($this->cacheKey($userId));

        if (! is_array($entry) || ! is_string($entry['hash'] ?? null)) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'El código OTP expiró o no fue solicitado. Solicite uno nuevo (válido 5 minutos).',
            ]);
        }

        if (! Hash::check($normalized, $entry['hash'])) {
            $entry['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;

            if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($this->cacheKey($userId));

                throw ValidationException::withMessages([
                    self::ERROR_KEY => 'Demasiados intentos con un código incorrecto. Solicite una clave OTP nueva.',
                ]);
            }

            $this->store($userId, $entry);

            throw ValidationException::withMessages([
                self::ERROR_KEY => 'El código OTP es incorrecto.',
            ]);
        }

        if (! hash_equals((string) ($entry['fingerprint'] ?? ''), $fingerprint)) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'El descuento o el carrito cambiaron desde que se solicitó la clave OTP. Solicite una nueva.',
            ]);
        }

        if ($consume) {
            Cache::forget($this->cacheKey($userId));
            Cache::put($this->usedCacheKey($userId, $normalized), true, self::TTL_SECONDS);
        }
    }

    /**
     * @param  callable(): void  $callback
     */
    private function withLock(User $cashier, callable $callback): void
    {
        try {
            Cache::lock('pos_discount.otp.lock.'.$cashier->getKey(), 10)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'La clave OTP se está verificando en otra operación. Intente de nuevo.',
            ]);
        }
    }

    /**
     * @param  array{hash: string, fingerprint: string, attempts: int, expires_at: int}  $entry
     */
    private function store(int $userId, array $entry): void
    {
        $remaining = (int) $entry['expires_at'] - now()->getTimestamp();

        if ($remaining <= 0) {
            Cache::forget($this->cacheKey($userId));

            return;
        }

        Cache::put($this->cacheKey($userId), $entry, $remaining);
    }

    private function generateUnusedCode(int $userId): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

            if (! Cache::has($this->usedCacheKey($userId, $code))) {
                return $code;
            }
        }

        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    private function cacheKey(int $userId): string
    {
        return 'pos_discount.otp.'.$userId;
    }

    private function usedCacheKey(int $userId, string $code): string
    {
        return 'pos_discount.otp.used.'.$userId.'.'.$code;
    }
}
