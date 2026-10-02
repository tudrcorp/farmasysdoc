<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalPrinterMode;
use App\Enums\SaleStatus;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Models\Sale;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Encola documentos fiscales para el agente local y gestiona reintentos y resoluciones manuales.
 */
final class FiscalDocumentRegistrar
{
    public function __construct(
        private FiscalDocumentPayloadBuilder $payloads,
        private FiscalPrinterResolver $printers,
    ) {}

    /**
     * Encola la factura fiscal de una venta de caja según el modo de la máquina de la caja:
     * Activa → factura real; Simulación → documento de prueba (la caja sigue facturando como hoy);
     * Desactivada, sin máquina, venta a crédito o traslado interno → null (flujo actual).
     */
    public function registerInvoice(Sale $sale, ?User $cashier, ?string $mixedVesPaymentMethod = null): ?FiscalDocument
    {
        if (! $this->saleRequiresFiscalInvoice($sale)) {
            return null;
        }

        $existing = $this->findForSale($sale, FiscalDocumentType::Invoice);
        if ($existing instanceof FiscalDocument) {
            return $existing;
        }

        $printer = $this->printers->forCashier($cashier, (int) $sale->branch_id);
        if (! $printer instanceof FiscalPrinter || $printer->currentMode() === FiscalPrinterMode::Disabled) {
            return null;
        }

        if ($printer->currentMode() === FiscalPrinterMode::Simulation) {
            try {
                $payload = $this->payloads->forInvoice($sale, $mixedVesPaymentMethod);
            } catch (Throwable $e) {
                // En simulación la caja factura con el sistema actual: un error aquí no debe molestar al cajero.
                report($e);

                return null;
            }
        } else {
            $payload = $this->payloads->forInvoice($sale, $mixedVesPaymentMethod);
        }

        return $this->createForSale(
            $sale,
            $printer,
            FiscalDocumentType::Invoice,
            $payload,
            $cashier?->email ?? $cashier?->name ?? 'sistema',
            simulation: $printer->currentMode() === FiscalPrinterMode::Simulation,
        );
    }

    /**
     * Encola la nota de crédito de una venta anulada cuya factura ya salió por máquina fiscal.
     */
    public function registerCreditNote(Sale $sale, string $actor): ?FiscalDocument
    {
        $invoice = $this->findForSale($sale, FiscalDocumentType::Invoice);

        if (! $invoice instanceof FiscalDocument || $invoice->simulation || $invoice->status !== FiscalDocumentStatus::Printed) {
            return null;
        }

        $existing = $this->findForSale($sale, FiscalDocumentType::CreditNote);
        if ($existing instanceof FiscalDocument) {
            return $existing;
        }

        $invoice->loadMissing('fiscalPrinter');
        $printer = $invoice->fiscalPrinter;

        // La nota de crédito sale por la misma máquina que emitió la factura; si está inactiva se emite a mano.
        if (! $printer instanceof FiscalPrinter || ! $printer->is_active || $printer->currentMode() !== FiscalPrinterMode::Active) {
            return null;
        }

        return $this->createForSale(
            $sale,
            $printer,
            FiscalDocumentType::CreditNote,
            $this->payloads->forCreditNote($sale, $invoice),
            $actor,
        );
    }

    public function requestReport(FiscalPrinter $printer, FiscalDocumentType $type, string $actor): FiscalDocument
    {
        if (! $type->isReport()) {
            throw ValidationException::withMessages([
                'type' => 'Solo se pueden solicitar reportes X o Z.',
            ]);
        }

        if ($printer->currentMode() !== FiscalPrinterMode::Active) {
            throw ValidationException::withMessages([
                'mode' => 'Los reportes X/Z solo se piden a máquinas en modo «Activa»; en los otros modos el puerto lo usa el sistema actual.',
            ]);
        }

        $document = FiscalDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'fiscal_printer_id' => $printer->id,
            'sale_id' => null,
            'type' => $type,
            'status' => FiscalDocumentStatus::Pending,
            'payload' => $this->payloads->forReport($type),
            'requested_by' => $actor,
        ]);

        AuditLogger::record(
            'fiscal_report_requested',
            'Fiscal · '.$type->label().' solicitado · '.$printer->name,
            FiscalDocument::class,
            $document->id,
            $type->label(),
            ['module' => 'fiscal', 'fiscal_printer_id' => $printer->id],
        );

        return $document;
    }

    /**
     * Reencola un documento fallido, opcionalmente en otra máquina fiscal.
     */
    public function retry(FiscalDocument $document, string $actor, ?FiscalPrinter $printer = null): FiscalDocument
    {
        return DB::transaction(function () use ($document, $actor, $printer): FiscalDocument {
            $locked = FiscalDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== FiscalDocumentStatus::Failed) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden reintentar documentos en estado «Fallido».',
                ]);
            }

            $locked->forceFill([
                'status' => FiscalDocumentStatus::Pending,
                'fiscal_printer_id' => $printer?->id ?? $locked->fiscal_printer_id,
                'claimed_at' => null,
                'started_at' => null,
                'printer_counter_before' => null,
                'error_code' => null,
                'error_message' => null,
                'resolved_by' => $actor,
            ])->save();

            return $locked;
        });
    }

    /**
     * Cierra a mano un documento en revisión tras verificar físicamente la máquina fiscal.
     * Con número fiscal queda «Impreso»; sin él, «Fallido» (no salió y se puede reintentar).
     */
    public function resolveManually(FiscalDocument $document, ?string $fiscalNumber, string $actor, ?string $notes = null): FiscalDocument
    {
        return DB::transaction(function () use ($document, $fiscalNumber, $actor, $notes): FiscalDocument {
            $locked = FiscalDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [FiscalDocumentStatus::NeedsReview, ...FiscalDocumentStatus::inFlight()], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se resuelven a mano documentos en revisión o atascados en el agente.',
                ]);
            }

            $fiscalNumber = filled($fiscalNumber) ? trim((string) $fiscalNumber) : null;

            $locked->forceFill([
                'status' => $fiscalNumber !== null ? FiscalDocumentStatus::Printed : FiscalDocumentStatus::Failed,
                'fiscal_number' => $fiscalNumber ?? $locked->fiscal_number,
                'printed_at' => $fiscalNumber !== null ? ($locked->printed_at ?? now()) : null,
                'error_message' => trim(($locked->error_message ? $locked->error_message."\n" : '').'[Resuelto a mano] '.($notes ?? '')),
                'resolved_by' => $actor,
            ])->save();

            return $locked;
        });
    }

    /**
     * Mueve los documentos pendientes (aún no tomados por el agente) a otra máquina activa de la sucursal,
     * p. ej. cuando una máquina se daña. Los simulados se descartan.
     */
    public function reassignPending(FiscalPrinter $from, FiscalPrinter $to, string $actor): int
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['fiscal_printer_id' => 'Seleccione otra máquina fiscal.']);
        }

        if ($to->currentMode() !== FiscalPrinterMode::Active || ! $to->is_active || (int) $to->branch_id !== (int) $from->branch_id) {
            throw ValidationException::withMessages([
                'fiscal_printer_id' => 'La máquina destino debe estar activa, en modo «Activa» y en la misma sucursal.',
            ]);
        }

        return DB::transaction(function () use ($from, $to, $actor): int {
            FiscalDocument::query()
                ->where('fiscal_printer_id', $from->id)
                ->where('status', FiscalDocumentStatus::Pending)
                ->where('simulation', true)
                ->where('is_test', false)
                ->update(['status' => FiscalDocumentStatus::Cancelled, 'resolved_by' => $actor, 'updated_at' => now()]);

            $moved = FiscalDocument::query()
                ->where('fiscal_printer_id', $from->id)
                ->where('status', FiscalDocumentStatus::Pending)
                ->where('simulation', false)
                ->where('is_test', false)
                ->update(['fiscal_printer_id' => $to->id, 'resolved_by' => $actor, 'updated_at' => now()]);

            AuditLogger::record(
                'fiscal_documents_reassigned',
                'Fiscal · '.$moved.' documento(s) reasignados de '.$from->name.' a '.$to->name,
                FiscalPrinter::class,
                $from->id,
                $from->name,
                ['module' => 'fiscal', 'to_fiscal_printer_id' => $to->id, 'count' => $moved],
            );

            return $moved;
        });
    }

    public function cancel(FiscalDocument $document, string $actor): FiscalDocument
    {
        return DB::transaction(function () use ($document, $actor): FiscalDocument {
            $locked = FiscalDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [FiscalDocumentStatus::Pending, FiscalDocumentStatus::Failed], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden anular documentos pendientes o fallidos (nunca impresos).',
                ]);
            }

            $locked->forceFill([
                'status' => FiscalDocumentStatus::Cancelled,
                'resolved_by' => $actor,
            ])->save();

            return $locked;
        });
    }

    private function saleRequiresFiscalInvoice(Sale $sale): bool
    {
        return $sale->status === SaleStatus::Completed
            && $sale->payment_method !== 'credito_cliente'
            && ! $sale->isInternalBranchTransfer();
    }

    private function findForSale(Sale $sale, FiscalDocumentType $type): ?FiscalDocument
    {
        return FiscalDocument::query()
            ->where('sale_id', $sale->id)
            ->where('type', $type)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createForSale(Sale $sale, FiscalPrinter $printer, FiscalDocumentType $type, array $payload, string $actor, bool $simulation = false): FiscalDocument
    {
        try {
            return FiscalDocument::query()->create([
                'uuid' => (string) Str::uuid(),
                'fiscal_printer_id' => $printer->id,
                'sale_id' => $sale->id,
                'type' => $type,
                'status' => FiscalDocumentStatus::Pending,
                'simulation' => $simulation,
                'payload' => $payload,
                'expected_total_ves' => $payload['expected']['total_ves'] ?? null,
                'requested_by' => $actor,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->findForSale($sale, $type) ?? throw new RuntimeException('No se pudo registrar el documento fiscal.');
        }
    }
}
