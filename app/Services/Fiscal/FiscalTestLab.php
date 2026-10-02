<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Services\Audit\AuditLogger;
use App\Support\Fiscal\FiscalPaymentCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Laboratorio fiscal: pruebas sobre una máquina fiscal real sin crear ventas, sin mover inventario,
 * caja ni cuentas por cobrar. Solo genera documentos fiscales marcados como prueba (is_test).
 *
 * Niveles: 1) lectura de estado, 2) ticket no fiscal, 3) simulación con venta ficticia,
 * 4) factura real de prueba + nota de crédito automática (deja rastro fiscal neto cero).
 */
final class FiscalTestLab
{
    public function __construct(private FiscalDocumentPayloadBuilder $payloads) {}

    public function requestStatusRead(FiscalPrinter $printer, string $actor): FiscalDocument
    {
        return $this->create($printer, FiscalDocumentType::StatusRead, $this->payloads->forReport(FiscalDocumentType::StatusRead), $actor);
    }

    /**
     * @param  list<string>  $lines
     */
    public function requestNonFiscalTicket(FiscalPrinter $printer, array $lines, string $actor): FiscalDocument
    {
        $payload = $this->payloads->forNonFiscalTicket(array_merge(
            ['*** PRUEBA - NO FISCAL ***', 'FARMADOC · '.$printer->name, now()->format('d/m/Y H:i')],
            $lines,
        ));

        return $this->create($printer, FiscalDocumentType::NonFiscalTicket, $payload, $actor);
    }

    /**
     * Nivel 3: el agente arma los comandos de la venta ficticia sin imprimir ni abrir el puerto.
     *
     * @param  array{items: list<array{description: string, quantity: float|int|string, unit_price_ves: float|int|string, tax: string}>, payment_method: string, customer_document?: ?string, customer_name?: ?string}  $sale
     */
    public function requestSimulation(FiscalPrinter $printer, array $sale, string $actor): FiscalDocument
    {
        $payload = $this->payloads->forTestInvoice($sale, autoCreditNote: false);

        return $this->create($printer, FiscalDocumentType::Invoice, $payload, $actor, simulation: true);
    }

    /**
     * Nivel 4: factura real mínima; al imprimirse, se encola su nota de crédito automáticamente.
     *
     * @param  array{items: list<array{description: string, quantity: float|int|string, unit_price_ves: float|int|string, tax: string}>, payment_method: string, customer_document?: ?string, customer_name?: ?string}  $sale
     */
    public function requestTestInvoice(FiscalPrinter $printer, array $sale, string $actor): FiscalDocument
    {
        $slots = is_array($printer->payment_slots) ? $printer->payment_slots : [];

        if (! FiscalPaymentCodes::isValidSlot($slots[$sale['payment_method']] ?? null)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Ese medio de pago no tiene número asignado en esta máquina.',
            ]);
        }

        if (blank($sale['customer_document'] ?? null) || blank($sale['customer_name'] ?? null)) {
            throw ValidationException::withMessages([
                'customer_document' => 'La nota de crédito automática exige RIF/C.I. y nombre del cliente.',
            ]);
        }

        $payload = $this->payloads->forTestInvoice($sale, autoCreditNote: true);
        $max = (float) config('fiscal.test_lab.max_invoice_total_ves', 10);

        if ((float) $payload['expected']['total_ves'] > $max + 0.000001) {
            throw ValidationException::withMessages([
                'items' => 'El total de la factura de prueba ('.number_format((float) $payload['expected']['total_ves'], 2, ',', '.')
                    .' Bs) supera el máximo permitido ('.number_format($max, 2, ',', '.').' Bs).',
            ]);
        }

        $document = $this->create($printer, FiscalDocumentType::Invoice, $payload, $actor);

        AuditLogger::record(
            'fiscal_test_invoice_requested',
            'Fiscal · Factura REAL de prueba solicitada · '.$printer->name.' · '.number_format((float) $payload['expected']['total_ves'], 2, ',', '.').' Bs',
            FiscalDocument::class,
            $document->id,
            $document->uuid,
            ['module' => 'fiscal', 'fiscal_printer_id' => $printer->id, 'total_ves' => $payload['expected']['total_ves']],
        );

        return $document;
    }

    /**
     * Tras imprimirse una factura de prueba con número fiscal, encola su nota de crédito (una sola vez).
     */
    public function queueAutoCreditNote(FiscalDocument $invoice): ?FiscalDocument
    {
        $payload = is_array($invoice->payload) ? $invoice->payload : [];

        if (! $invoice->is_test
            || $invoice->simulation
            || $invoice->type !== FiscalDocumentType::Invoice
            || blank($invoice->fiscal_number)
            || ! ($payload['auto_credit_note'] ?? false)) {
            return null;
        }

        return DB::transaction(function () use ($invoice): ?FiscalDocument {
            $existing = FiscalDocument::query()
                ->where('related_document_id', $invoice->id)
                ->where('type', FiscalDocumentType::CreditNote)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof FiscalDocument) {
                return $existing;
            }

            $invoice->loadMissing('fiscalPrinter');

            $creditNote = $this->create(
                $invoice->fiscalPrinter,
                FiscalDocumentType::CreditNote,
                $this->payloads->forCreditNoteFromInvoice($invoice),
                'laboratorio (automática)',
                relatedDocumentId: $invoice->id,
            );

            AuditLogger::record(
                'fiscal_test_credit_note_queued',
                'Fiscal · Nota de crédito automática de la factura de prueba Nº '.$invoice->fiscal_number,
                FiscalDocument::class,
                $creditNote->id,
                $creditNote->uuid,
                ['module' => 'fiscal', 'invoice_document_id' => $invoice->id],
            );

            return $creditNote;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function create(
        FiscalPrinter $printer,
        FiscalDocumentType $type,
        array $payload,
        string $actor,
        bool $simulation = false,
        ?int $relatedDocumentId = null,
    ): FiscalDocument {
        return FiscalDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'fiscal_printer_id' => $printer->id,
            'sale_id' => null,
            'related_document_id' => $relatedDocumentId,
            'type' => $type,
            'status' => FiscalDocumentStatus::Pending,
            'simulation' => $simulation,
            'is_test' => true,
            'payload' => $payload,
            'expected_total_ves' => $payload['expected']['total_ves'] ?? null,
            'requested_by' => $actor,
        ]);
    }
}
