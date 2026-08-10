<?php

namespace App\Services\Inventory;

use App\Models\User;
use App\Support\Inventory\NotifyAdministratorsInventoryAuditOtp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class InventoryAuditOtpService
{
    public const TTL_SECONDS = 180;

    public function __construct(
        private readonly NotifyAdministratorsInventoryAuditOtp $notifier,
    ) {}

    /**
     * Genera un OTP de 6 dígitos, lo guarda hasheado en caché (TTL 3 min) y lo envía a administradores.
     *
     * @param  array{
     *     product_name?: string|null,
     *     branch_name?: string|null,
     *     changes?: list<string>
     * }  $context
     */
    public function issue(User $manager, array $context = []): string
    {
        if (! $this->actorRequiresOtp($manager)) {
            throw ValidationException::withMessages([
                'otp_code' => 'Solo gerentes requieren código OTP para auditoría de inventario.',
            ]);
        }

        $userId = (int) $manager->getKey();
        $code = $this->generateUnusedCode($userId);

        Cache::put(
            $this->cacheKey($userId),
            Hash::make($code),
            self::TTL_SECONDS,
        );

        /** @var list<string> $changes */
        $changes = array_values(array_filter(
            $context['changes'] ?? [],
            fn (mixed $line): bool => is_string($line) && filled($line),
        ));

        $this->notifier->notify(
            manager: $manager,
            otpCode: $code,
            productName: filled($context['product_name'] ?? null) ? (string) $context['product_name'] : null,
            branchName: filled($context['branch_name'] ?? null) ? (string) $context['branch_name'] : null,
            changes: $changes,
            ttlSeconds: self::TTL_SECONDS,
        );

        return $code;
    }

    public function verifyAndConsume(User $manager, ?string $code): void
    {
        $normalized = preg_replace('/\D/', '', (string) $code) ?? '';

        if (strlen($normalized) !== 6) {
            throw ValidationException::withMessages([
                'otp_code' => 'Ingrese el código OTP de 6 dígitos enviado a los administradores.',
            ]);
        }

        $userId = (int) $manager->getKey();

        if ($this->wasRecentlyConsumed($userId, $normalized)) {
            throw ValidationException::withMessages([
                'otp_code' => 'Esta clave OTP ya fue utilizada. Solicite una nueva para realizar otro cambio.',
            ]);
        }

        $key = $this->cacheKey($userId);
        $hash = Cache::get($key);

        if (! is_string($hash) || $hash === '') {
            throw ValidationException::withMessages([
                'otp_code' => 'El código OTP expiró o no fue solicitado. Solicite uno nuevo (válido 3 minutos).',
            ]);
        }

        if (! Hash::check($normalized, $hash)) {
            throw ValidationException::withMessages([
                'otp_code' => 'El código OTP es incorrecto.',
            ]);
        }

        $this->invalidate($manager);
        $this->markConsumed($userId, $normalized);
    }

    /**
     * Inhabilita cualquier OTP activo del gerente (p. ej. tras aplicar un cambio con éxito).
     */
    public function invalidate(User $manager): void
    {
        Cache::forget($this->cacheKey((int) $manager->getKey()));
    }

    public function actorRequiresOtp(?User $actor): bool
    {
        return $actor instanceof User
            && $actor->isManager()
            && ! $actor->isAdministrator();
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
        return 'inventory_audit.otp.'.$userId;
    }

    private function usedCacheKey(int $userId, string $code): string
    {
        return 'inventory_audit.otp.used.'.$userId.'.'.$code;
    }
}
