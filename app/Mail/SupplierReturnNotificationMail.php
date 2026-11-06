<?php

namespace App\Mail;

use App\Models\SupplierReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierReturnNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public SupplierReturn $supplierReturn,
        public string $recipientName,
        public string $headline,
        public array $lines,
        public string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner-agreement-notification',
            with: ['actionLabel' => 'Ver devolución'],
        );
    }
}
