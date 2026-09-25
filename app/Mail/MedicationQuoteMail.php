<?php

namespace App\Mail;

use App\Models\MedicationQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MedicationQuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MedicationQuote $quote,
        public string $pdfContents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cotización '.$this->quote->number.' · '.$this->quote->requester_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.medication-quote',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfContents, $this->quote->fileName())
                ->withMime('application/pdf'),
        ];
    }
}
