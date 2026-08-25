<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ManualBdvConciliationOtpMail extends Mailable
{
    public function __construct(
        public string $otpCode,
        public string $actorName,
        public bool $actorIsAdministrator,
        public bool $fromPosCashier = false,
        public ?string $branchName = null,
        public ?string $reference = null,
        public ?string $amount = null,
        public ?string $payerDocument = null,
        public ?string $payerPhone = null,
        public ?string $destinationPhone = null,
        public ?string $paymentDate = null,
        public ?string $originBank = null,
        public int $ttlMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OTP conciliación manual Pago Móvil: '.$this->otpCode,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.manual-bdv-conciliation-otp',
        );
    }
}
