<?php

namespace App\Support\Audit;

final class AuditLogEventPresentation
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'login' => 'Inicio de sesión',
            'logout' => 'Cierre de sesión',
            'login_failed' => 'Login fallido',
            'page_view' => 'Vista en panel',
            'http_request' => 'Petición HTTP en panel',
            'created' => 'Creación (datos)',
            'updated' => 'Actualización (datos)',
            'deleted' => 'Eliminación (datos)',
        ];
    }

    public static function label(string $event): string
    {
        $key = strtolower(trim($event));

        return self::labels()[$key] ?? $event;
    }

    public static function badgeColor(string $event): string
    {
        return match (strtolower(trim($event))) {
            'login' => 'success',
            'logout' => 'gray',
            'login_failed' => 'danger',
            'page_view' => 'info',
            'http_request' => 'warning',
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Opciones para filtros (valor técnico => etiqueta).
     *
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return self::labels();
    }
}
