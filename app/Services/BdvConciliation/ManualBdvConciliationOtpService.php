<?php

namespace App\Services\BdvConciliation;

use App\Enums\OtpCodePurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\OtpCodeService;
use Illuminate\Validation\ValidationException;

/**
 * OTP de conciliación manual de Pago Móvil BDV (panel y caja).
 * La clave la ven gerentes de la sucursal y administradores en «Claves OTP», nunca quien la solicitó.
 */
final class ManualBdvConciliationOtpService
{
    public function __construct(
        private readonly OtpCodeService $otpCodes,
    ) {}

    /**
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
    public function issue(User $actor, ?int $branchId, array $context = []): OtpCode
    {
        if (! $this->actorCanRegister($actor)) {
            throw ValidationException::withMessages([
                'otp_code' => 'Solo gerentes y administradores pueden solicitar un OTP de conciliación manual.',
            ]);
        }

        return $this->otpCodes->issue(
            purpose: OtpCodePurpose::ManualBdvConciliation,
            requester: $actor,
            branchId: $branchId,
            summary: $this->summary($context),
            details: $this->details($context),
        );
    }

    /**
     * OTP solicitado por el cajero en caja: la autoriza el gerente de la sucursal o un administrador.
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
    public function issueForPosCashier(User $cashier, int $branchId, array $context = []): OtpCode
    {
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'otp_code' => 'No se pudo determinar la sucursal del cajero para generar el OTP.',
            ]);
        }

        return $this->otpCodes->issue(
            purpose: OtpCodePurpose::PosBdvConciliation,
            requester: $cashier,
            branchId: $branchId,
            summary: $this->summary($context),
            details: $this->details($context),
        );
    }

    public function verifyAndConsume(User $actor, ?string $code, OtpCodePurpose $purpose, string $usedIn): void
    {
        $this->otpCodes->consume($purpose, $actor, $code, $usedIn);
    }

    public function actorCanRegister(?User $actor): bool
    {
        return $actor instanceof User
            && ($actor->isAdministrator() || $actor->isManager());
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function summary(array $context): ?string
    {
        $summary = implode(' · ', array_filter([
            filled($context['reference'] ?? null) ? 'Ref. '.$context['reference'] : null,
            $context['amount'] ?? null,
            $context['branch_name'] ?? null,
        ]));

        return $summary !== '' ? $summary : null;
    }

    /**
     * @param  array<string, string|null>  $context
     * @return list<string>
     */
    private function details(array $context): array
    {
        $labels = [
            'branch_name' => 'Sucursal',
            'reference' => 'Referencia',
            'amount' => 'Monto',
            'payer_document' => 'Cédula del pagador',
            'payer_phone' => 'Teléfono del pagador',
            'destination_phone' => 'Teléfono destino',
            'payment_date' => 'Fecha del pago',
            'origin_bank' => 'Banco de origen',
        ];

        $details = [];
        foreach ($labels as $key => $label) {
            if (filled($context[$key] ?? null)) {
                $details[] = $label.': '.$context[$key];
            }
        }

        return $details;
    }
}
