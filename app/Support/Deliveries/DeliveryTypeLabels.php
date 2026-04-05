<?php

namespace App\Support\Deliveries;

use App\Support\Orders\PartnerOrderDeliverySync;

/**
 * Etiquetas legibles para `deliveries.delivery_type` (valores internos).
 */
final class DeliveryTypeLabels
{
    public const TYPE_MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return [
            PartnerOrderDeliverySync::DELIVERY_TYPE_PARTNER => 'Aliado · envío a domicilio',
        ];
    }

    /**
     * Opciones del formulario (incluye registro manual).
     *
     * @return array<string, string>
     */
    public static function formOptions(): array
    {
        return [
            ...self::filterOptions(),
            self::TYPE_MANUAL => 'Registro manual',
        ];
    }

    public static function label(?string $type): string
    {
        if ($type === null || $type === '') {
            return '—';
        }

        return match ($type) {
            PartnerOrderDeliverySync::DELIVERY_TYPE_PARTNER => 'Aliado · envío a domicilio',
            self::TYPE_MANUAL => 'Registro manual',
            default => $type,
        };
    }
}
