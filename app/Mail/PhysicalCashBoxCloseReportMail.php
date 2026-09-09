<?php

namespace App\Mail;

use App\Models\PhysicalCashBoxCloseReport;
use App\Support\Cash\PhysicalCashBoxCloseVariance;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PhysicalCashBoxCloseReportMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        public array $report,
        public ?PhysicalCashBoxCloseReport $closeReport,
        public string $pdfContents,
        public string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        $branch = (string) ($this->report['branch_name'] ?? 'Sucursal');
        $cashier = (string) ($this->report['cashier_name'] ?? 'Cajero');

        return new Envelope(
            subject: 'Cierre de caja física · '.$branch.' · '.$cashier,
        );
    }

    public function content(): Content
    {
        $cash = is_array($this->report['cash_box_reconciliation'] ?? null)
            ? $this->report['cash_box_reconciliation']
            : [];
        $pos = is_array($this->report['pos_reconciliation'] ?? null)
            ? $this->report['pos_reconciliation']
            : ['lines' => []];
        $hasMismatch = (bool) ($cash['has_mismatch'] ?? false) || (bool) ($pos['has_mismatch'] ?? false);

        return new Content(
            html: 'emails.physical-cash-box-close-report',
            with: [
                'appName' => (string) config('app.name'),
                'branchName' => (string) ($this->report['branch_name'] ?? '—'),
                'cashierName' => (string) ($this->report['cashier_name'] ?? '—'),
                'openedAtLabel' => (string) ($this->report['opened_at_label'] ?? '—'),
                'closedAtLabel' => (string) ($this->report['closed_at_label'] ?? '—'),
                'statusLabel' => $hasMismatch ? 'Descuadre' : 'Cuadrado',
                'hasMismatch' => $hasMismatch,
                'expectedUsdLabel' => $this->formatMoney((float) ($cash['expected_usd'] ?? 0), 'USD'),
                'declaredUsdLabel' => $this->formatMoney((float) ($cash['declared_usd'] ?? 0), 'USD'),
                'differenceUsdLabel' => $this->formatSignedMoney((float) ($cash['difference_usd'] ?? 0), 'USD'),
                'usdStatusLabel' => PhysicalCashBoxCloseVariance::statusLabel((float) ($cash['difference_usd'] ?? 0)),
                'expectedVesLabel' => $this->formatMoney((float) ($cash['expected_ves'] ?? 0), 'Bs'),
                'declaredVesLabel' => $this->formatMoney((float) ($cash['declared_ves'] ?? 0), 'Bs'),
                'differenceVesLabel' => $this->formatSignedMoney((float) ($cash['difference_ves'] ?? 0), 'Bs'),
                'vesStatusLabel' => PhysicalCashBoxCloseVariance::statusLabel((float) ($cash['difference_ves'] ?? 0)),
                'posLines' => is_array($pos['lines'] ?? null) ? $pos['lines'] : [],
                'pdfFilename' => $this->pdfFilename,
                'logoPath' => public_path('images/logos/farmadoc-ligth.png'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->pdfContents === '') {
            return [];
        }

        return [
            Attachment::fromData(fn (): string => $this->pdfContents, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }

    private function formatMoney(float $amount, string $suffix): string
    {
        return number_format($amount, 2, ',', '.').' '.$suffix;
    }

    private function formatSignedMoney(float $amount, string $suffix): string
    {
        $formatted = number_format(abs($amount), 2, ',', '.').' '.$suffix;

        if (abs($amount) < 0.005) {
            return $formatted;
        }

        return ($amount > 0 ? '+' : '-').$formatted;
    }
}
