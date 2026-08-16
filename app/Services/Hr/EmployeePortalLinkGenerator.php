<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Support\Notifications\WhatsAppLink;
use App\Support\Qr\QrPngWithLogo;

final class EmployeePortalLinkGenerator
{
    public function qrImageUrl(string $url, int $size = 240): string
    {
        return app(QrPngWithLogo::class)->dataUri($url, size: $size);
    }

    public function loginUrl(): string
    {
        return route('employee-portal.login', absolute: true);
    }

    public function invitationMessage(Employee $employee): string
    {
        return "Hola {$employee->fullName()}, entra al portal de empleados de Farmadoc con tu cédula o teléfono:\n{$this->loginUrl()}";
    }

    /**
     * @return array{
     *     employee: Employee,
     *     qr: string,
     *     whatsappUrl: ?string,
     *     loginUrl: string,
     *     expiresLabel: string
     * }
     */
    public function inviteViewData(Employee $employee): array
    {
        $loginUrl = $this->loginUrl();

        return [
            'employee' => $employee,
            'loginUrl' => $loginUrl,
            'qr' => $this->qrImageUrl($loginUrl),
            'whatsappUrl' => WhatsAppLink::buildWaMeUrl($employee->phone, $this->invitationMessage($employee)),
            'expiresLabel' => 'El portal está siempre disponible',
        ];
    }
}
