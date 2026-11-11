<?php

namespace App\Services\Inventory;

use App\Enums\OtpCodePurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\OtpCodeService;
use Illuminate\Validation\ValidationException;

/**
 * OTP que autoriza a un gerente a aplicar cambios de existencia, costo o categoría en una auditoría.
 * La clave la ven administradores y otros gerentes de la sucursal en «Claves OTP».
 */
final class InventoryAuditOtpService
{
    public function __construct(
        private readonly OtpCodeService $otpCodes,
    ) {}

    /**
     * @param  array{
     *     product_name?: string|null,
     *     branch_name?: string|null,
     *     branch_id?: int|null,
     *     changes?: list<string>
     * }  $context
     */
    public function issue(User $manager, array $context = []): OtpCode
    {
        if (! $this->actorRequiresOtp($manager)) {
            throw ValidationException::withMessages([
                'otp_code' => 'Solo gerentes requieren código OTP para auditoría de inventario.',
            ]);
        }

        $branchId = isset($context['branch_id']) && is_numeric($context['branch_id'])
            ? (int) $context['branch_id']
            : null;

        $summary = implode(' · ', array_filter([
            filled($context['product_name'] ?? null) ? (string) $context['product_name'] : null,
            filled($context['branch_name'] ?? null) ? (string) $context['branch_name'] : null,
        ]));

        return $this->otpCodes->issue(
            purpose: OtpCodePurpose::InventoryAudit,
            requester: $manager,
            branchId: $branchId,
            summary: $summary !== '' ? $summary : null,
            details: array_values($context['changes'] ?? []),
        );
    }

    public function verifyAndConsume(User $manager, ?string $code, string $usedIn = 'Auditoría de inventario'): void
    {
        $this->otpCodes->consume(OtpCodePurpose::InventoryAudit, $manager, $code, $usedIn);
    }

    /**
     * Anula cualquier OTP vigente del gerente.
     */
    public function invalidate(User $manager): void
    {
        $this->otpCodes->cancelActive(OtpCodePurpose::InventoryAudit, $manager, 'Anulada por el sistema.');
    }

    public function actorRequiresOtp(?User $actor): bool
    {
        return $actor instanceof User
            && $actor->isManager()
            && ! $actor->isAdministrator();
    }
}
