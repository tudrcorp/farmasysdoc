<?php

namespace App\Services\Sales;

use App\Enums\OtpCodePurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\OtpCodeService;
use App\Support\Sales\PosDiscountOtpRequirement;
use Illuminate\Validation\ValidationException;

/**
 * OTP para registrar en caja una venta con descuento manual: atada a la huella del descuento
 * ({@see PosDiscountOtpRequirement}). La autoriza el gerente de la sucursal o un administrador.
 */
final class PosDiscountOtpService
{
    public const ERROR_KEY = 'pos_discount_otp_code';

    public function __construct(
        private readonly OtpCodeService $otpCodes,
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
    public function issue(User $cashier, int $branchId, string $fingerprint, array $context = []): OtpCode
    {
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'No se pudo determinar la sucursal del cajero para generar el OTP.',
            ]);
        }

        $summary = implode(' · ', array_filter([
            filled($context['discount_amount'] ?? null) ? 'Descuento '.$context['discount_amount'] : null,
            filled($context['total'] ?? null) ? 'Total '.$context['total'] : null,
            $context['branch_name'] ?? null,
        ]));

        $details = array_values(array_filter([
            filled($context['client_name'] ?? null) ? 'Cliente: '.$context['client_name'] : null,
            filled($context['sale_percent'] ?? null) ? 'Descuento sobre el total: '.$context['sale_percent'] : null,
            ...array_map(fn (string $line): string => 'Producto: '.$line, $context['lines'] ?? []),
        ]));

        return $this->otpCodes->issue(
            purpose: OtpCodePurpose::PosDiscount,
            requester: $cashier,
            branchId: $branchId,
            summary: $summary !== '' ? $summary : null,
            details: $details,
            fingerprint: $fingerprint,
        );
    }

    /**
     * Verifica sin consumir (aviso temprano en caja). La OTP se consume con verifyAndConsume() justo antes de crear la venta.
     */
    public function assertValid(User $cashier, ?string $code, string $fingerprint): void
    {
        $this->otpCodes->assertValid(OtpCodePurpose::PosDiscount, $cashier, $code, $fingerprint, self::ERROR_KEY);
    }

    public function verifyAndConsume(User $cashier, ?string $code, string $fingerprint, string $usedIn = 'Caja · Venta con descuento manual'): void
    {
        $this->otpCodes->consume(OtpCodePurpose::PosDiscount, $cashier, $code, $usedIn, $fingerprint, self::ERROR_KEY);
    }
}
