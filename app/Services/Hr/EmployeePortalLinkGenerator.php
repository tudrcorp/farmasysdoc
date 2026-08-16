<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Support\Facades\URL;

final class EmployeePortalLinkGenerator
{
    public function temporaryUrl(Employee $employee, int $days = 7): string
    {
        return URL::temporarySignedRoute(
            'employee-portal.enter',
            now()->addDays($days),
            ['employee' => $employee->getKey()],
            absolute: true,
        );
    }

    public function qrImageUrl(string $url, int $size = 240): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size.'&data='.rawurlencode($url);
    }

    public function loginUrl(): string
    {
        return route('employee-portal.login', absolute: true);
    }

    public function invitationMessage(Employee $employee, string $url, int $days = 7): string
    {
        return "Hola {$employee->fullName()}, entra al portal de empleados de Farmadoc. Puedes entrar siempre con tu cédula o teléfono aquí:\n{$this->loginUrl()}\n\nTambién puedes usar este acceso directo (vence en {$days} días):\n{$url}";
    }

    /**
     * @return array{
     *     employee: Employee,
     *     url: string,
     *     qr: string,
     *     whatsappUrl: ?string,
     *     loginUrl: string,
     *     expiresLabel: string,
     *     days: int
     * }
     */
    public function inviteViewData(Employee $employee, int $days = 7): array
    {
        $url = $this->temporaryUrl($employee, $days);
        $loginUrl = $this->loginUrl();

        return [
            'employee' => $employee,
            'url' => $url,
            'loginUrl' => $loginUrl,
            'qr' => $this->qrImageUrl($loginUrl),
            'whatsappUrl' => WhatsAppLink::buildWaMeUrl($employee->phone, $this->invitationMessage($employee, $url, $days)),
            'expiresLabel' => 'El portal está siempre disponible',
            'days' => $days,
        ];
    }
}
