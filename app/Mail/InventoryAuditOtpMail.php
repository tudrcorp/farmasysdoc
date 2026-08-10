<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InventoryAuditOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otpCode,
        public string $managerName,
        public ?string $productName = null,
        public ?string $branchName = null,
        public int $ttlMinutes = 3,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OTP auditoría de inventario: '.$this->otpCode,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inventory-audit-otp',
        );
    }
}
