<?php

namespace App\Mail;

use App\Models\EmployeeRequest;
use App\Services\Hr\EmployeeRequestDecisionNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeRequestDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmployeeRequest $employeeRequest,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(EmployeeRequestDecisionNotifier::class)->headline($this->employeeRequest),
        );
    }

    public function content(): Content
    {
        $notifier = app(EmployeeRequestDecisionNotifier::class);

        return new Content(
            view: 'emails.employee-request-decision',
            with: [
                'headline' => $notifier->headline($this->employeeRequest),
                'nextStep' => $notifier->nextStep($this->employeeRequest),
            ],
        );
    }
}
