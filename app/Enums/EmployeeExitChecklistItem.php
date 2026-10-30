<?php

namespace App\Enums;

enum EmployeeExitChecklistItem: string
{
    case Uniform = 'uniform';
    case Equipment = 'equipment';
    case SystemUser = 'system_user';
    case CashBox = 'cash_box';
    case Loans = 'loans';

    public function label(): string
    {
        return match ($this) {
            self::Uniform => 'Uniforme entregado',
            self::Equipment => 'Equipos y llaves entregados',
            self::SystemUser => 'Usuario del sistema desactivado',
            self::CashBox => 'Caja cuadrada',
            self::Loans => 'Préstamos revisados',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
