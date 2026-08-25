<?php

namespace App\Services\BdvConciliation;

use App\Enums\VenezuelanPagoMovilBank;
use App\Models\Branch;
use App\Models\ConciliationBdv;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ManualBdvConciliationService
{
    public const MANUAL_BDV_CODE = 'MANUAL';

    public function __construct(
        private readonly ManualBdvConciliationOtpService $otpService,
        private readonly BdvPagomovilConciliationService $pagomovilService,
    ) {}

    public function canRegister(?User $actor): bool
    {
        return $this->otpService->actorCanRegister($actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(User $actor, array $data): ConciliationBdv
    {
        if (! $this->canRegister($actor)) {
            throw ValidationException::withMessages([
                'otp_code' => 'Solo gerentes y administradores pueden registrar una conciliación manual.',
            ]);
        }

        $this->otpService->verifyAndConsume($actor, isset($data['otp_code']) ? (string) $data['otp_code'] : null);

        $branchId = (int) ($data['branch_id'] ?? 0);
        $this->assertBranchAllowed($actor, $branchId);

        $branch = Branch::query()->find($branchId);
        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal seleccionada no existe.',
            ]);
        }

        $destinationPhone = trim((string) ($data['destination_phone'] ?? ''));
        if ($destinationPhone === '') {
            $destinationPhone = $this->pagomovilService->resolveCommercePhone($branchId);
        }

        $originBank = (string) ($data['origin_bank'] ?? '');
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $reference = trim((string) ($data['reference'] ?? ''));
        $payerDocument = trim((string) ($data['payer_document'] ?? ''));
        $payerPhone = trim((string) ($data['payer_phone'] ?? ''));
        $paymentDate = (string) ($data['payment_date'] ?? now()->toDateString());

        $payload = [
            'manual' => true,
            'cedulaPagador' => $payerDocument,
            'telefonoPagador' => $payerPhone,
            'telefonoDestino' => $destinationPhone,
            'referencia' => $reference,
            'fechaPago' => $paymentDate,
            'importe' => number_format($amount, 2, '.', ''),
            'bancoOrigen' => $originBank,
        ];

        $record = ConciliationBdv::query()->create([
            'branch_id' => $branchId,
            'user_id' => $actor->id,
            'sale_id' => null,
            'environment' => $this->pagomovilService->resolveEnvironment(),
            'payer_document' => $payerDocument,
            'payer_phone' => $payerPhone,
            'destination_phone' => $destinationPhone,
            'reference' => $reference,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'origin_bank' => $originBank,
            'req_ced' => false,
            'bdv_http_status' => null,
            'bdv_code' => self::MANUAL_BDV_CODE,
            'bdv_message' => 'Conciliación registrada manualmente (sin consulta a BDV).',
            'bdv_payload' => $payload,
            'bdv_response' => [
                'manual' => true,
                'note' => 'Registrada sin consulta a la API BDV',
            ],
            'conciliated_at' => now(),
            'is_manual' => true,
            'created_by' => $actor->email ?? $actor->name ?? 'sistema',
        ]);

        AuditLogger::record(
            event: 'bdv_pagomovil_conciliation_manual',
            description: 'Conciliación PM BDV registrada de forma manual.',
            auditableType: ConciliationBdv::class,
            auditableId: $record->getKey(),
            auditableLabel: $reference !== '' ? $reference : null,
            properties: [
                'branch_id' => $branchId,
                'branch_name' => $branch->name,
                'reference' => $reference,
                'amount' => $amount,
                'record_id' => $record->id,
                'actor_role' => $actor->isAdministrator() ? 'ADMINISTRADOR' : 'GERENTE',
            ],
            user: $actor,
        );

        return $record;
    }

    /**
     * Conciliación manual desde caja tras OTP enviado al gerente de sucursal y administradores.
     *
     * @param  array{
     *     cedulaPagador?: string,
     *     telefonoPagador?: string,
     *     telefonoDestino?: string,
     *     referencia?: string,
     *     fechaPago?: string,
     *     importe?: string|float|int,
     *     bancoOrigen?: string,
     *     reqCed?: bool
     * }  $candidate
     */
    public function registerFromPos(User $cashier, array $candidate, ?string $otpCode): ConciliationBdv
    {
        $this->otpService->verifyAndConsume($cashier, $otpCode);

        $branchId = (int) ($cashier->branch_id ?? 0);
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'otp_code' => 'El cajero no tiene sucursal asignada para registrar la conciliación.',
            ]);
        }

        $branch = Branch::query()->find($branchId);
        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'otp_code' => 'La sucursal del cajero no existe.',
            ]);
        }

        $destinationPhone = trim((string) ($candidate['telefonoDestino'] ?? ''));
        if ($destinationPhone === '') {
            $destinationPhone = $this->pagomovilService->resolveCommercePhone($branchId);
        }

        $amount = round((float) ($candidate['importe'] ?? 0), 2);
        $reference = preg_replace('/\D+/', '', trim((string) ($candidate['referencia'] ?? ''))) ?? '';
        if ($reference === '') {
            throw ValidationException::withMessages([
                'otp_code' => 'Indique la referencia del Pago Móvil (4 a 6 dígitos) antes de conciliar de forma manual.',
            ]);
        }

        $payerDocument = trim((string) ($candidate['cedulaPagador'] ?? ''));
        $payerPhone = trim((string) ($candidate['telefonoPagador'] ?? ''));
        $paymentDate = (string) ($candidate['fechaPago'] ?? now()->toDateString());
        $originBank = (string) ($candidate['bancoOrigen'] ?? '');

        $payload = [
            'manual' => true,
            'via' => 'pos_caja',
            'otp_authorized' => true,
            'cedulaPagador' => $payerDocument,
            'telefonoPagador' => $payerPhone,
            'telefonoDestino' => $destinationPhone,
            'referencia' => $reference,
            'fechaPago' => $paymentDate,
            'importe' => number_format($amount, 2, '.', ''),
            'bancoOrigen' => $originBank,
            'reqCed' => (bool) ($candidate['reqCed'] ?? false),
        ];

        $record = ConciliationBdv::query()->create([
            'branch_id' => $branchId,
            'user_id' => $cashier->id,
            'sale_id' => null,
            'environment' => $this->pagomovilService->resolveEnvironment(),
            'payer_document' => $payerDocument,
            'payer_phone' => $payerPhone,
            'destination_phone' => $destinationPhone,
            'reference' => $reference,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'origin_bank' => $originBank,
            'req_ced' => (bool) ($candidate['reqCed'] ?? false),
            'bdv_http_status' => null,
            'bdv_code' => self::MANUAL_BDV_CODE,
            'bdv_message' => 'Conciliación registrada manualmente desde caja (autorizada con OTP).',
            'bdv_payload' => $payload,
            'bdv_response' => [
                'manual' => true,
                'via' => 'pos_caja',
                'otp_authorized' => true,
                'data' => [
                    'referencia' => $reference,
                ],
                'note' => 'Registrada sin consulta a la API BDV tras OTP de gerente/administradores',
            ],
            'conciliated_at' => now(),
            'is_manual' => true,
            'created_by' => $cashier->email ?? $cashier->name ?? 'sistema',
        ]);

        AuditLogger::record(
            event: 'pos_caja_bdv_conciliation_manual',
            description: 'Caja · Conciliación PM BDV registrada de forma manual (OTP).',
            auditableType: ConciliationBdv::class,
            auditableId: $record->getKey(),
            auditableLabel: $reference !== '' ? $reference : null,
            properties: [
                'branch_id' => $branchId,
                'branch_name' => $branch->name,
                'reference' => $reference,
                'amount' => $amount,
                'record_id' => $record->id,
                'otp_authorized' => true,
            ],
            user: $cashier,
        );

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     branch_name: string|null,
     *     reference: string|null,
     *     amount: string|null,
     *     payer_document: string|null,
     *     payer_phone: string|null,
     *     destination_phone: string|null,
     *     payment_date: string|null,
     *     origin_bank: string|null
     * }
     */
    public function otpContextFromForm(array $data): array
    {
        $branchId = (int) ($data['branch_id'] ?? 0);
        $branchName = $branchId > 0
            ? (string) (Branch::query()->whereKey($branchId)->value('name') ?? '')
            : null;

        $amountRaw = $data['amount'] ?? null;
        $amountLabel = null;
        if ($amountRaw !== null && $amountRaw !== '') {
            $amountLabel = number_format((float) $amountRaw, 2, ',', '.').' VES';
        }

        $originBank = isset($data['origin_bank']) ? (string) $data['origin_bank'] : '';
        $originBankLabel = null;
        if ($originBank !== '') {
            $bank = VenezuelanPagoMovilBank::tryFrom($originBank);
            $originBankLabel = $bank instanceof VenezuelanPagoMovilBank
                ? $bank->optionLabel()
                : $originBank;
        }

        $paymentDate = isset($data['payment_date']) ? (string) $data['payment_date'] : '';
        $paymentDateLabel = null;
        if ($paymentDate !== '') {
            $paymentDateLabel = Carbon::parse($paymentDate)->format('d/m/Y');
        }

        return [
            'branch_name' => filled($branchName) ? $branchName : null,
            'reference' => filled($data['reference'] ?? null) ? (string) $data['reference'] : null,
            'amount' => $amountLabel,
            'payer_document' => filled($data['payer_document'] ?? null) ? (string) $data['payer_document'] : null,
            'payer_phone' => filled($data['payer_phone'] ?? null) ? (string) $data['payer_phone'] : null,
            'destination_phone' => filled($data['destination_phone'] ?? null) ? (string) $data['destination_phone'] : null,
            'payment_date' => $paymentDateLabel,
            'origin_bank' => $originBankLabel,
        ];
    }

    private function assertBranchAllowed(User $actor, int $branchId): void
    {
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'branch_id' => 'Seleccione una sucursal.',
            ]);
        }

        $allowed = array_column($this->pagomovilService->branchOptionsForUser($actor), 'id');

        if (! in_array($branchId, $allowed, true)) {
            throw ValidationException::withMessages([
                'branch_id' => 'No tiene permiso para registrar una conciliación en esa sucursal.',
            ]);
        }
    }
}
