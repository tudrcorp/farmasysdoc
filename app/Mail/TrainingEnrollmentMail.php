<?php

namespace App\Mail;

use App\Models\TrainingEnrollment;
use App\Services\Training\TrainingEnrollmentNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainingEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TrainingEnrollment $enrollment,
        public string $event,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(TrainingEnrollmentNotifier::class)->headline($this->enrollment, $this->event),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.training-enrollment',
            with: [
                'headline' => app(TrainingEnrollmentNotifier::class)->headline($this->enrollment, $this->event),
                'session' => $this->enrollment->session,
            ],
        );
    }
}
