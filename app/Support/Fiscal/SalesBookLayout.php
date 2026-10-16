<?php

namespace App\Support\Fiscal;

use Illuminate\Support\Carbon;

/**
 * Columnas del Libro de Ventas (formato SENIAT usado por la empresa), compartidas por Excel, PDF y pantalla.
 */
final class SalesBookLayout
{
    /**
     * @return list<array{key: string, label: string, money?: bool, width: float}>
     */
    public static function columns(): array
    {
        return [
            ['key' => 'operation_number', 'label' => 'Nº DE OPERACIÓN', 'width' => 7],
            ['key' => 'date', 'label' => 'FECHA DE EMISIÓN', 'width' => 11],
            ['key' => 'document_type', 'label' => 'TIPO DE DOCUMENTO', 'width' => 16],
            ['key' => 'first_document', 'label' => 'FACTURA / TICKET FISCAL INICIAL', 'width' => 12],
            ['key' => 'last_document', 'label' => 'FACTURA / TICKET FISCAL FINAL (Nº DE CONTROL)', 'width' => 12],
            ['key' => 'affected_document', 'label' => 'FACTURA AFECTADA', 'width' => 11],
            ['key' => 'customer_name', 'label' => 'NOMBRE COMPLETO / RAZÓN SOCIAL DEL CLIENTE', 'width' => 32],
            ['key' => 'customer_rif', 'label' => 'Nº DE RIF O CÉDULA', 'width' => 14],
            ['key' => 'taxpayer_type', 'label' => 'TIPO DE CONTRIBUYENTE', 'width' => 16],
            ['key' => 'total', 'label' => 'TOTAL VENTAS INCLUYENDO IVA', 'money' => true, 'width' => 15],
            ['key' => 'exempt', 'label' => 'EXENTAS, EXONERADAS O NO SUJETAS', 'money' => true, 'width' => 15],
            ['key' => 'export', 'label' => 'MONTO EXPORTACIÓN', 'money' => true, 'width' => 11],
            ['key' => 'contributor_base', 'label' => 'BASE IMPONIBLE', 'money' => true, 'width' => 15],
            ['key' => 'contributor_tax', 'label' => 'IVA', 'money' => true, 'width' => 13],
            ['key' => 'non_contributor_base', 'label' => 'BASE IMPONIBLE', 'money' => true, 'width' => 15],
            ['key' => 'non_contributor_tax', 'label' => 'IVA', 'money' => true, 'width' => 13],
            ['key' => 'igtf_base', 'label' => 'BASE IGTF', 'money' => true, 'width' => 11],
            ['key' => 'igtf_tax', 'label' => 'IMPUESTO IGTF', 'money' => true, 'width' => 11],
            ['key' => 'igtf_rate', 'label' => 'ALÍCUOTA IGTF', 'width' => 8],
            ['key' => 'vat_rate', 'label' => 'ALÍCUOTA IVA', 'width' => 8],
            ['key' => 'retention_issued_on', 'label' => 'FECHA DE EMISIÓN DEL COMPROBANTE DE RETENCIÓN', 'width' => 12],
            ['key' => 'retention_voucher', 'label' => 'Nº DEL COMPROBANTE DE RETENCIÓN', 'width' => 16],
            ['key' => 'retained', 'label' => 'MONTO DE RETENCIÓN EFECTUADA POR LOS CLIENTES', 'money' => true, 'width' => 14],
            ['key' => 'printer_serial', 'label' => 'Nº DE MÁQUINA FISCAL', 'width' => 13],
        ];
    }

    /**
     * Grupos de encabezado sobre las columnas (índice inicial, índice final, texto).
     *
     * @return list<array{0: int, 1: int, 2: string}>
     */
    public static function groups(): array
    {
        return [
            [9, 11, 'TOTAL VENTAS'],
            [12, 13, 'VENTAS INTERNAS A CONTRIBUYENTES'],
            [14, 15, 'VENTAS INTERNAS A NO CONTRIBUYENTES'],
            [16, 18, 'IGTF'],
            [20, 22, 'RETENCIONES DE IVA'],
        ];
    }

    /**
     * Valor de una celda ya presentado (texto); los montos se devuelven como float.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string|int>  $header
     */
    public static function value(array $row, string $key, array $header): string|float|int|null
    {
        return match ($key) {
            'date' => filled($row['date'] ?? null) ? Carbon::parse($row['date'])->format('d/m/Y') : null,
            'retention_issued_on' => filled($row['retention_issued_on'] ?? null) ? Carbon::parse($row['retention_issued_on'])->format('d/m/Y') : null,
            'vat_rate' => ($row['vat_rate'] ?? null) !== null ? self::percent((float) $row['vat_rate']) : null,
            'igtf_rate' => in_array($row['document_type'] ?? '', ['REPORTE Z', 'NOTA DE CRÉDITO', 'NOTA DE DÉBITO', 'FACTURA'], true)
                ? self::percent((float) ($header['igtf_rate'] ?? 0))
                : null,
            'total', 'exempt', 'export', 'contributor_base', 'contributor_tax', 'non_contributor_base',
            'non_contributor_tax', 'igtf_base', 'igtf_tax', 'retained' => (float) ($row['amounts'][$key] ?? 0),
            default => $row[$key] ?? null,
        };
    }

    public static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').'%';
    }

    public static function money(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    /**
     * Filas del resumen de débitos fiscales: [concepto, ítem base, clave base, ítem débito, clave débito, ítem retenido, clave retenido].
     *
     * @return list<array{0: string, 1: string, 2: ?string, 3: string, 4: ?string, 5: string, 6: ?string}>
     */
    public static function summaryLines(): array
    {
        return [
            ['Total ventas internas no gravadas', '40', 'item_40', '', null, '', null],
            ['Ventas de exportación', '41', 'item_41', '', null, '', null],
            ['Ventas internas gravadas por alícuota general', '42', 'item_42', '43', 'item_43', '66', 'item_66'],
            ['Ventas internas gravadas por alícuota general más adicional', '442', 'item_442', '452', 'item_452', '', null],
            ['Ventas internas gravadas por alícuota reducida', '443', 'item_443', '453', 'item_453', '', null],
            ['Total de ventas y débitos fiscales', '46', 'item_46', '47', 'item_47', '', 'item_66'],
        ];
    }
}
