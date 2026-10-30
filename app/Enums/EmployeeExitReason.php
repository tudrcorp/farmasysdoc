<?php

namespace App\Enums;

enum EmployeeExitReason: string
{
    case Resignation = 'resignation';
    case JustifiedDismissal = 'justified_dismissal';
    case UnjustifiedDismissal = 'unjustified_dismissal';
    case MutualAgreement = 'mutual_agreement';
    case ContractEnd = 'contract_end';
    case Retirement = 'retirement';
    case Death = 'death';

    public function label(): string
    {
        return match ($this) {
            self::Resignation => 'Renuncia',
            self::JustifiedDismissal => 'Despido justificado',
            self::UnjustifiedDismissal => 'Despido injustificado',
            self::MutualAgreement => 'Mutuo acuerdo',
            self::ContractEnd => 'Fin de contrato',
            self::Retirement => 'Jubilación',
            self::Death => 'Fallecimiento',
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
