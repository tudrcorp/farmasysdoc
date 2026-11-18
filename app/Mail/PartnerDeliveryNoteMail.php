<?php

namespace App\Mail;

use App\Models\PartnerAgreementSale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Nota de entrega de una venta por convenio, adjunta en PDF, para el afiliado.
 */
class PartnerDeliveryNoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PartnerAgreementSale $sale,
        public string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nota de entrega '.$this->sale->number.' · Farmadoc');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.partner-delivery-note');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->sale->fileName())->withMime('application/pdf'),
        ];
    }
}
