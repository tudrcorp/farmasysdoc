<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum FiscalDocumentType: string
{
    use HasSpanishLabels;

    case Invoice = 'factura';
    case CreditNote = 'nota_credito';
    case XReport = 'reporte_x';
    case ZReport = 'reporte_z';
    case StatusRead = 'lectura_estado';
    case NonFiscalTicket = 'ticket_no_fiscal';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Factura',
            self::CreditNote => 'Nota de crédito',
            self::XReport => 'Reporte X',
            self::ZReport => 'Reporte Z',
            self::StatusRead => 'Lectura de estado',
            self::NonFiscalTicket => 'Ticket no fiscal',
        };
    }

    public function isReport(): bool
    {
        return in_array($this, [self::XReport, self::ZReport], true);
    }

    /**
     * Solo facturas y notas de crédito reciben número fiscal y se concilian contra el total esperado.
     */
    public function requiresFiscalNumber(): bool
    {
        return in_array($this, [self::Invoice, self::CreditNote], true);
    }
}
