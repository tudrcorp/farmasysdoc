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
    case Capitated = 'capitado';
    case Prepaid = 'prepagado';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Crédito',
            self::Cash => 'Contado',
            self::Integral => 'Integral',
            self::Capitated => 'Capitado',
            self::Prepaid => 'Prepagado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Credit => 'warning',
            self::Cash => 'success',
            self::Integral => 'info',
            self::Capitated => 'primary',
            self::Prepaid => 'gray',
        };
    }

    /**
     * Crédito e integral operan con un límite de crédito aprobado; contado, capitado y
     * prepagado se pagan por adelantado o al momento, sin límite de crédito.
     */
    public function usesCreditLimit(): bool
    {
        return in_array($this, [self::Credit, self::Integral], true);
    }
}
