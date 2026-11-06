<?php

namespace App\Mail;

use App\Models\PartnerCompanyAgreement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerAgreementNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public PartnerCompanyAgreement $agreement,
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
        return new Content(view: 'emails.partner-agreement-notification');
    }
}
