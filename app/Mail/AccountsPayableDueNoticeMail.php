<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountsPayableDueNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $recipientName,
        public string $headline,
        public array $lines,
        public string $pdfPath,
        public string $pdfDisk,
        public string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.accounts-payable-due-notice');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk($this->pdfDisk, $this->pdfPath)
                ->as(basename($this->pdfPath))
                ->withMime('application/pdf'),
        ];
    }
}
