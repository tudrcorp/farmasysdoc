<?php

namespace App\Enums;

enum PurchaseEntryCurrency: string
{
    case USD = 'USD';
    case VES = 'VES';

    public function moneyPrefix(): string
    {
        return match ($this) {
            self::USD => '$',
            self::VES => 'Bs.',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::USD => 'USD',
            self::VES => 'VES (bolívares)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function checkboxOptions(): array
    {
        return [
            self::USD->value => self::USD->label(),
            self::VES->value => self::VES->label(),
        ];
    }
}
