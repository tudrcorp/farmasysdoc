<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PosDiscountOtpMail extends Mailable
{
    /**
     * @param  list<string>  $discountedLines
     */
    public function __construct(
        public string $otpCode,
        public string $cashierName,
        public ?string $branchName = null,
        public ?string $clientName = null,
        public ?string $salePercent = null,
        public array $discountedLines = [],
        public ?string $discountAmount = null,
        public ?string $total = null,
        public int $ttlMinutes = 5,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OTP venta con descuento en caja: '.$this->otpCode,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pos-discount-otp',
        );
    }
}
