<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo con el enlace para crear (invitación) o cambiar (olvidó su clave) la contraseña
 * del panel de aliados, con la imagen de dale.
 */
class PartnerPanelAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $companyName,
        public string $email,
        public string $actionUrl,
        public bool $isInvitation,
        public int $expiresInHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isInvitation
            ? 'Su acceso a dale · Aliados'
            : 'Cambie su clave de dale · Aliados');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.partner-panel-access');
    }
}
