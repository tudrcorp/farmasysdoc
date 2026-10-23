<?php

namespace App\Support\Hr;

use App\Models\EmployeeRequest;
use App\Services\Hr\EmployeePortalAccess;

/**
 * Contador de decisiones no vistas por el empleado (campana del portal y número en el ícono de la PWA).
 * Lee el id directamente de la sesión para no cargar el empleado: es una sola consulta con índice.
 */
final class EmployeePortalNotifications
{
    public static function unreadCountForSession(): ?int
    {
        $payload = session(EmployeePortalAccess::SESSION_KEY);
        $employeeId = is_array($payload) ? ($payload['employee_id'] ?? null) : null;

        if (blank($employeeId) || (int) ($payload['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return self::unreadCountFor((int) $employeeId);
    }

    public static function unreadCountFor(int $employeeId): int
    {
        return EmployeeRequest::query()
            ->where('employee_id', $employeeId)
            ->unseenDecisions()
            ->count();
    }

    public static function markAllSeen(int $employeeId): int
    {
        return EmployeeRequest::query()
            ->where('employee_id', $employeeId)
            ->unseenDecisions()
            ->update(['employee_seen_at' => now()]);
    }
}
