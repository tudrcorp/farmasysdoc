<?php

namespace App\Services\Hr;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Mail\EmployeeRequestDecisionMail;
use App\Models\EmployeeRequest;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Envía al empleado la decisión sobre su solicitud. Si el empleado no tiene correo o teléfono,
 * o WhatsApp no está configurado, no hace nada; si el envío falla, lanza para que la cola reintente.
 */
final class EmployeeRequestDecisionNotifier
{
    public function __construct(private UltramsgWhatsAppClient $whatsApp) {}

    public function sendEmail(EmployeeRequest $request): void
    {
        $employee = $request->employee;

        if ($employee === null || ! $employee->hasPortalEmail()) {
            return;
        }

        Mail::to((string) $employee->email)->send(new EmployeeRequestDecisionMail($request, $this->portalUrl()));
    }

    public function sendWhatsApp(EmployeeRequest $request): void
    {
        $digits = WhatsAppLink::normalizePhoneDigits($request->employee?->phone);

        if ($digits === null || ! $this->whatsApp->isEnabled()) {
            return;
        }

        if (! $this->whatsApp->sendTextMessage($digits, $this->whatsAppBody($request))) {
            throw new RuntimeException('No se pudo enviar el WhatsApp de la solicitud #'.$request->id.'.');
        }
    }

    public function headline(EmployeeRequest $request): string
    {
        $type = mb_strtolower($request->type->label());

        return $request->status === EmployeeRequestStatus::Approved
            ? "Tu solicitud de {$type} fue aprobada"
            : "Tu solicitud de {$type} fue rechazada";
    }

    public function nextStep(EmployeeRequest $request): ?string
    {
        if ($request->status !== EmployeeRequestStatus::Approved) {
            return null;
        }

        return $request->type === EmployeeRequestType::EmploymentCertificate
            ? 'Ya puedes descargar tu constancia desde el portal del empleado.'
            : 'Puedes ver el detalle en el portal del empleado.';
    }

    public function portalUrl(): string
    {
        return route('employee-portal.requests');
    }

    private function whatsAppBody(EmployeeRequest $request): string
    {
        $lines = [
            '*Farmadoc · Portal del empleado*',
            '',
            'Hola, '.($request->employee?->first_name ?? '').'.',
            $this->headline($request).'.',
        ];

        if ($request->periodLabel() !== null) {
            $lines[] = 'Fechas: '.$request->periodLabel();
        }

        if (filled($request->admin_note)) {
            $lines[] = 'Nota de RR. HH.: '.$request->admin_note;
        }

        if ($this->nextStep($request) !== null) {
            $lines[] = $this->nextStep($request);
        }

        $lines[] = '';
        $lines[] = $this->portalUrl();

        return implode("\n", $lines);
    }
}
