<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Tipo de cuenta de Farmadoc desde la que se paga una cuenta por pagar.
 */
enum PaymentBankAccountType: string
{
    use HasSpanishLabels;

    case Checking = 'corriente';
    case Savings = 'ahorro';
    case Electronic = 'electronica';
    case Foreign = 'extranjera';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Corriente',
            self::Savings => 'Ahorro',
            self::Electronic => 'Electrónica',
            self::Foreign => 'Extranjera',
        };
    }

    /**
     * Electrónicas y extranjeras no están en el catálogo de bancos venezolanos: el nombre se escribe a mano.
     */
    public function requiresManualBankName(): bool
    {
        return in_array($this, [self::Electronic, self::Foreign], true);
    }
}
