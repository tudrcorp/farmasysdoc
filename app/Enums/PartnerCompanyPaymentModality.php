<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Acuerdo de negociación (modalidad de pago) pactado con la compañía aliada.
 */
enum PartnerCompanyPaymentModality: string
{
    use HasSpanishLabels;

    case Credit = 'credito';
    case Cash = 'contado';
    case Integral = 'integral';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Crédito',
            self::Cash => 'Contado',
            self::Integral => 'Integral',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Credit => 'warning',
            self::Cash => 'success',
            self::Integral => 'info',
        };
    }

    /**
     * Crédito e integral operan con un límite de crédito aprobado.
     */
    public function usesCreditLimit(): bool
    {
        return $this !== self::Cash;
    }
}
