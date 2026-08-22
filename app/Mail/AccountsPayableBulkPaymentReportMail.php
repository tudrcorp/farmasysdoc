<?php

namespace App\Mail;

use App\Models\AccountsPayable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AccountsPayableBulkPaymentReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, AccountsPayable>  $accountsPayables
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        public Collection $accountsPayables,
        public array $report,
        public string $pdfContents,
        public string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->accountsPayables->count();
        $supplier = (string) ($this->accountsPayables->first()?->supplier_name ?: 'proveedor');

        return new Envelope(
            subject: 'Comprobante de pago · '.$count.' factura'.($count === 1 ? '' : 's').' · '.$supplier,
        );
    }

    public function content(): Content
    {
        $first = $this->accountsPayables->first();

        return new Content(
            html: 'emails.accounts-payable-bulk-payment-report',
            with: [
                'supplierName' => filled($first?->supplier_name) ? (string) $first->supplier_name : '—',
                'supplierTaxId' => filled($first?->supplier_tax_id) ? (string) $first->supplier_tax_id : '—',
                'invoiceCount' => $this->accountsPayables->count(),
                'paidAtLabel' => (string) ($this->report['paid_at'] ?? '—'),
                'paymentReference' => filled($this->report['payment_reference'] ?? null)
                    ? (string) $this->report['payment_reference']
                    : '—',
                'paymentMethodLabel' => (string) ($this->report['payment_method'] ?? '—'),
                'paymentFormLabel' => (string) ($this->report['payment_form'] ?? '—'),
                'amountPaidUsdLabel' => number_format((float) ($this->report['total_paid_usd'] ?? 0), 2, ',', '.').' USD',
                'amountPaidVesLabel' => number_format((float) ($this->report['total_paid_ves'] ?? 0), 2, ',', '.').' Bs',
                'invoiceLines' => $this->invoiceLines(),
                'hasPaymentProof' => $this->accountsPayables->contains(
                    static fn (AccountsPayable $record): bool => filled($record->payment_proof_path),
                ),
                'pdfFilename' => $this->pdfFilename,
                'logoPath' => public_path('images/logos/farmadoc-ligth.png'),
            ],
        );
    }

    /**
     * @return list<array{invoice: string, purchase: string, usd: string, ves: string}>
     */
    private function invoiceLines(): array
    {
        $rows = collect($this->report['rows'] ?? []);

        return $rows->map(static function (mixed $row): array {
            if (! is_array($row)) {
                return [
                    'invoice' => '—',
                    'purchase' => '—',
                    'usd' => '0,00 USD',
                    'ves' => '0,00 Bs',
                ];
            }

            $ap = $row['accounts_payable'] ?? null;

            return [
                'invoice' => $ap instanceof AccountsPayable && filled($ap->supplier_invoice_number)
                    ? (string) $ap->supplier_invoice_number
                    : '—',
                'purchase' => filled($row['purchase_number'] ?? null) ? (string) $row['purchase_number'] : '—',
                'usd' => number_format((float) ($row['total_paid_usd'] ?? 0), 2, ',', '.').' USD',
                'ves' => number_format((float) ($row['total_paid_ves'] ?? 0), 2, ',', '.').' Bs',
            ];
        })->values()->all();
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfContents, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
