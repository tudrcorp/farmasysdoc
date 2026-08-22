<?php

namespace App\Mail;

use App\Models\AccountsPayable;
use App\Models\PurchaseHistory;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Purchases\PurchaseHistoryEntryType;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountsPayablePaymentReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AccountsPayable $accountsPayable,
        public string $pdfContents,
        public string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        $invoice = filled($this->accountsPayable->supplier_invoice_number)
            ? (string) $this->accountsPayable->supplier_invoice_number
            : '#'.$this->accountsPayable->getKey();

        return new Envelope(
            subject: 'Comprobante de pago · factura '.$invoice,
        );
    }

    public function content(): Content
    {
        $this->accountsPayable->loadMissing(['purchase', 'branch', 'paymentHistories']);
        $payment = $this->latestPayment();

        return new Content(
            html: 'emails.accounts-payable-payment-report',
            with: $this->viewData($payment),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(?PurchaseHistory $payment): array
    {
        $ap = $this->accountsPayable;
        $paidAt = $payment?->paid_at ?? $ap->paid_at;

        return [
            'supplierName' => filled($ap->supplier_name) ? (string) $ap->supplier_name : '—',
            'supplierTaxId' => filled($ap->supplier_tax_id) ? (string) $ap->supplier_tax_id : '—',
            'invoiceNumber' => filled($ap->supplier_invoice_number) ? (string) $ap->supplier_invoice_number : '—',
            'controlNumber' => filled($ap->supplier_control_number) ? (string) $ap->supplier_control_number : '—',
            'purchaseNumber' => filled($ap->purchase?->purchase_number) ? (string) $ap->purchase->purchase_number : '—',
            'branchName' => filled($ap->branch?->name) ? (string) $ap->branch->name : '—',
            'paidAtLabel' => $paidAt?->format('d/m/Y H:i') ?? '—',
            'paymentReference' => filled($payment?->payment_reference)
                ? (string) $payment->payment_reference
                : (filled($ap->payment_reference) ? (string) $ap->payment_reference : '—'),
            'paymentMethodLabel' => PurchaseHistoryPaymentMethod::label($payment?->payment_method),
            'paymentFormLabel' => PurchaseHistoryPaymentForm::label($payment?->payment_form),
            'amountPaidUsdLabel' => $this->formatMoney((float) ($payment?->amount_paid_usd ?? 0), 'USD'),
            'amountPaidVesLabel' => $this->formatMoney((float) ($payment?->amount_paid_ves ?? 0), 'Bs'),
            'statusLabel' => AccountsPayableStatus::label($ap->status),
            'hasPaymentProof' => filled($ap->payment_proof_path),
            'pdfFilename' => $this->pdfFilename,
            'logoPath' => public_path('images/logos/farmadoc-ligth.png'),
        ];
    }

    private function latestPayment(): ?PurchaseHistory
    {
        $payment = $this->accountsPayable->paymentHistories
            ->filter(static fn (PurchaseHistory $history): bool => $history->entry_type === PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR)
            ->sortByDesc(static fn (PurchaseHistory $history): string => ($history->paid_at?->format('YmdHis') ?? '0').'-'.$history->getKey())
            ->first();

        return $payment instanceof PurchaseHistory ? $payment : null;
    }

    private function formatMoney(float $amount, string $suffix): string
    {
        return number_format($amount, 2, ',', '.').' '.$suffix;
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
