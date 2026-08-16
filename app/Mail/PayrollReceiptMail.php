<?php

namespace App\Mail;

use App\Models\HrPayrollReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HrPayrollReceipt $receipt,
        public string $pdfContents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibo de nómina · '.$this->receipt->periodLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payroll-receipt',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfContents, $this->receipt->fileName())
                ->withMime('application/pdf'),
        ];
    }
}
