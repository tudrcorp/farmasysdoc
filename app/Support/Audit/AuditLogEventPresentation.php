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
            'login' => 'Inicio de sesi'.chr(0xC3).chr(0xB3).'n',
            'logout' => 'Cierre de sesi'.chr(0xC3).chr(0xB3).'n',
            'login_failed' => 'Login fallido',
            'page_view' => 'Vista en panel',
            'http_request' => 'Petici'.chr(0xC3).chr(0xB3).'n HTTP en panel',
            'created' => 'Creaci'.chr(0xC3).chr(0xB3).'n (datos)',
            'updated' => 'Actualizaci'.chr(0xC3).chr(0xB3).'n (datos)',
            'deleted' => 'Eliminaci'.chr(0xC3).chr(0xB3).'n (datos)',
            'purchase_history_compra_contado_registered' => 'Hist'.chr(0xC3).chr(0xB3).'rico compras: compra al contado',
            'purchase_compra_contado_historic_written' => 'Compra: asiento en hist'.chr(0xC3).chr(0xB3).'rico (contado)',
            'purchase_history_cpp_payment_registered' => 'Hist'.chr(0xC3).chr(0xB3).'rico compras: pago a cuenta por pagar',
            'purchase_history_viewed' => 'Hist'.chr(0xC3).chr(0xB3).'rico compras: consulta de detalle',
            'purchase_cancellation_requested' => 'Compra: solicitud de anulaci'.chr(0xC3).chr(0xB3).'n',
            'purchase_annulled' => 'Compra: anulaci'.chr(0xC3).chr(0xB3).'n confirmada',
            'pos_caja_walk_in' => 'Caja: inicio venta mostrador',
            'pos_caja_client_picked_from_catalog' => 'Caja: cliente elegido desde cat'.chr(0xC3).chr(0xA1).'logo',
            'pos_caja_quick_client_created' => 'Caja: cliente nuevo (registro r'.chr(0xC3).chr(0xA1).'pido)',
            'pos_caja_quick_client_existing_doc' => 'Caja: documento ya existente (uso de cliente registrado)',
            'pos_caja_credit_confirmed' => 'Caja: confirmaci'.chr(0xC3).chr(0xB3).'n venta a cr'.chr(0xC3).chr(0xA9).'dito / CxC',
            'pos_caja_sale_completed' => 'Caja: venta registrada',
            'pos_caja_sale_blocked' => 'Caja: venta no registrada (bloqueo o validaci'.chr(0xC3).chr(0xB3).'n)',
            'pos_caja_bdv_conciliation_ok' => 'Caja: Pago M'.chr(0xC3).chr(0xB3).'vil conciliado con BDV',
            'pos_caja_bdv_conciliation_failed' => 'Caja: Pago M'.chr(0xC3).chr(0xB3).'vil no conciliado (BDV)',
            'pos_caja_bdv_modal_abandoned' => 'Caja: conciliaci'.chr(0xC3).chr(0xB3).'n BDV cerrada '.chr(0xC2).chr(0xB7).' cambio de m'.chr(0xC3).chr(0xA9).'todo',
            'pos_caja_close_pdf_downloaded' => 'Caja: descarga PDF cierre de caja',
            'pos_caja_fiscal_receipt_viewed' => 'Caja/comprobantes: vista comprobante fiscal',
            'pos_caja_delivery_note_viewed' => 'Caja/comprobantes: vista nota de entrega',
            'sale_transfer_created' => 'Traslado de venta: alta',
            'sale_transfer_viewed' => 'Traslado de venta: consulta de detalle',
            'sale_transfer_delivery_took' => 'Traslado de venta: tomado por delivery',
            'sale_transfer_completed' => 'Traslado de venta: completado',
            'sale_transfer_admin_status_changed' => 'Traslado de venta: cambio de estatus (admin/gerencia)',
            'sale_transfer_deleted' => 'Traslado de venta: eliminado',
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
            'purchase_cancellation_requested' => 'warning',
            'purchase_annulled' => 'danger',
            'pos_caja_walk_in' => 'gray',
            'pos_caja_client_picked_from_catalog' => 'info',
            'pos_caja_quick_client_created' => 'success',
            'pos_caja_quick_client_existing_doc' => 'warning',
            'pos_caja_credit_confirmed' => 'warning',
            'pos_caja_sale_completed' => 'success',
            'pos_caja_sale_blocked' => 'danger',
            'pos_caja_bdv_conciliation_ok' => 'success',
            'pos_caja_bdv_conciliation_failed' => 'danger',
            'pos_caja_bdv_modal_abandoned' => 'warning',
            'pos_caja_close_pdf_downloaded' => 'info',
            'pos_caja_fiscal_receipt_viewed' => 'gray',
            'pos_caja_delivery_note_viewed' => 'gray',
            'sale_transfer_created' => 'success',
            'sale_transfer_viewed' => 'gray',
            'sale_transfer_delivery_took' => 'warning',
            'sale_transfer_completed' => 'success',
            'sale_transfer_admin_status_changed' => 'warning',
            'sale_transfer_deleted' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Opciones para filtros del listado (valor interno => texto mostrado).
     *
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return self::labels();
    }
}
