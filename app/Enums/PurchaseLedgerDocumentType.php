<?php

namespace App\Enums;

enum PurchaseLedgerDocumentType: string
{
    case Factura = 'FACTURA';
    case ComprobanteDeRetencion = 'COMPROBANTE_DE_RETENCION';

    public function label(): string
    {
        return match ($this) {
            self::Factura => 'FACTURA',
            self::ComprobanteDeRetencion => 'COMPROBANTE DE RETENCION',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
