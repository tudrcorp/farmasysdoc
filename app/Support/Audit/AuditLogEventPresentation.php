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
            'purchase_history_compra_contado_registered' => 'Histórico compras: compra al contado',
            'purchase_compra_contado_historic_written' => 'Compra: asiento en histórico (contado)',
            'purchase_history_cpp_payment_registered' => 'Histórico compras: pago a cuenta por pagar',
            'purchase_history_viewed' => 'Histórico compras: consulta de detalle',
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
            'purchase_history_compra_contado_registered' => 'success',
            'purchase_compra_contado_historic_written' => 'success',
            'purchase_history_cpp_payment_registered' => 'info',
            'purchase_history_viewed' => 'gray',
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
