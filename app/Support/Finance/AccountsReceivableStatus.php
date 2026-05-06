<?php

namespace App\Support\Finance;

final class AccountsReceivableStatus
{
    public const POR_COBRAR = 'por_cobrar';

    public const COBRADO = 'cobrado';

    public const CANCELADO = 'cancelado';

    public static function label(?string $status): string
    {
        return match ($status) {
            self::POR_COBRAR => 'Por cobrar',
            self::COBRADO => 'Cobrado',
            self::CANCELADO => 'Cancelado',
            default => filled($status) ? (string) $status : '—',
        };
    }
}
