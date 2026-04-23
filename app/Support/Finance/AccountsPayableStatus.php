<?php

namespace App\Support\Finance;

/**
 * Estado operativo de una cuenta por pagar.
 */
final class AccountsPayableStatus
{
    public const POR_PAGAR = 'por_pagar';

    public const PAGADO = 'pagado';

    public const ANULADA = 'anulada';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::POR_PAGAR => 'Por pagar',
            self::PAGADO => 'Pagado',
            self::ANULADA => 'Anulada',
        ];
    }

    public static function label(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return self::options()[$value] ?? $value;
    }
}
