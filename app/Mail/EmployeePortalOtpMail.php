<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmployeePortalOtpMail extends Mailable
{
    public function __construct(
        public Employee $employee,
        public string $otpCode,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código para restablecer tu clave · Portal Farmadoc',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-portal-otp',
        );
    }
}
