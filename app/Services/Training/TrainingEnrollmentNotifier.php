<?php

namespace App\Services\Training;

use App\Jobs\NotifyTrainingEnrollmentJob;
use App\Mail\TrainingEnrollmentMail;
use App\Models\TrainingEnrollment;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Avisa al empleado de su inscripción o de una cancelación. Sin correo, teléfono o WhatsApp
 * configurado no hace nada; si el envío falla, lanza para que la cola reintente.
 */
final class TrainingEnrollmentNotifier
{
    public function __construct(private UltramsgWhatsAppClient $whatsApp) {}

    public function sendEmail(TrainingEnrollment $enrollment, string $event): void
    {
        $employee = $enrollment->employee;

        if (! $employee->hasPortalEmail()) {
            return;
        }

        Mail::to((string) $employee->email)->send(new TrainingEnrollmentMail($enrollment, $event, $this->portalUrl()));
    }

    public function sendWhatsApp(TrainingEnrollment $enrollment, string $event): void
    {
        $digits = WhatsAppLink::normalizePhoneDigits($enrollment->employee->phone);

        if ($digits === null || ! $this->whatsApp->isEnabled()) {
            return;
        }

        if (! $this->whatsApp->sendTextMessage($digits, $this->whatsAppBody($enrollment, $event))) {
            throw new RuntimeException('No se pudo enviar el WhatsApp de la inscripción #'.$enrollment->id.'.');
        }
    }

    public function headline(TrainingEnrollment $enrollment, string $event): string
    {
        $course = $enrollment->session->course->name;

        return $event === NotifyTrainingEnrollmentJob::EVENT_CANCELLED
            ? "Se canceló la capacitación {$course}"
            : "Te inscribieron en {$course}";
    }

    public function portalUrl(): string
    {
        return route('employee-portal.trainings');
    }

    private function whatsAppBody(TrainingEnrollment $enrollment, string $event): string
    {
        $session = $enrollment->session;

        $lines = [
            '*Farmadoc · Portal del empleado*',
            '',
            'Hola, '.$enrollment->employee->first_name.'.',
            $this->headline($enrollment, $event).'.',
            'Fecha: '.$session->scheduleLabel(),
        ];

        if ($event === NotifyTrainingEnrollmentJob::EVENT_ENROLLED) {
            $place = $session->location ?? $session->meeting_url;
            if (filled($place)) {
                $lines[] = 'Lugar: '.$place;
            }
        }

        $lines[] = '';
        $lines[] = $this->portalUrl();

        return implode("\n", $lines);
    }
}
