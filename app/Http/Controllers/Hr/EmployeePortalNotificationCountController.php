<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Support\Hr\EmployeePortalNotifications;
use Illuminate\Http\JsonResponse;

/**
 * Contador liviano para la campana y el ícono de la PWA. No toca Livewire ni carga el empleado.
 */
final class EmployeePortalNotificationCountController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(['count' => EmployeePortalNotifications::unreadCountForSession() ?? 0])
            ->header('Cache-Control', 'private, no-store');
    }
}
