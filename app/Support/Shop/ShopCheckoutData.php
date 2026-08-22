<?php

namespace App\Support\Shop;

/**
 * Datos confirmados en el checkout de la PWA, ya validados por el componente Livewire.
 */
final class ShopCheckoutData
{
    public const FULFILLMENT_DELIVERY = 'delivery';

    public const FULFILLMENT_PICKUP = 'pickup';

    public function __construct(
        public readonly string $name,
        public readonly string $documentType,
        public readonly string $documentNumber,
        public readonly string $phone,
        public readonly string $email,
        public readonly string $fulfillment,
        public readonly string $paymentMethod,
        public readonly string $address = '',
        public readonly string $city = '',
        public readonly string $state = '',
        public readonly ?int $branchId = null,
        public readonly ?string $deliveryNotes = null,
        public readonly ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['name'] ?? '')),
            documentType: (string) ($data['document_type'] ?? 'CC'),
            documentNumber: trim((string) ($data['document_number'] ?? '')),
            phone: trim((string) ($data['phone'] ?? '')),
            email: mb_strtolower(trim((string) ($data['email'] ?? ''))),
            fulfillment: (string) ($data['fulfillment'] ?? self::FULFILLMENT_DELIVERY),
            paymentMethod: (string) ($data['payment_method'] ?? 'pago_movil'),
            address: trim((string) ($data['address'] ?? '')),
            city: trim((string) ($data['city'] ?? '')),
            state: trim((string) ($data['state'] ?? '')),
            branchId: isset($data['branch_id']) && $data['branch_id'] !== '' ? (int) $data['branch_id'] : null,
            deliveryNotes: filled($data['delivery_notes'] ?? null) ? trim((string) $data['delivery_notes']) : null,
            notes: filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        );
    }

    public function isPickup(): bool
    {
        return $this->fulfillment === self::FULFILLMENT_PICKUP;
    }

    /**
     * Métodos de pago ofrecidos en la app.
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function paymentMethods(): array
    {
        return [
            'pago_movil' => ['label' => 'Pago móvil', 'hint' => 'Te enviamos los datos al confirmar'],
            'efectivo' => ['label' => 'Efectivo', 'hint' => 'Pagas al recibir o al retirar'],
            'transferencia' => ['label' => 'Transferencia', 'hint' => 'Bs. a cuenta nacional'],
            'tarjeta' => ['label' => 'Punto de venta', 'hint' => 'Débito o crédito al entregar'],
        ];
    }

    /**
     * Métodos de la pasarela web (Pago móvil y transferencia).
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function webPaymentMethods(): array
    {
        return array_intersect_key(self::paymentMethods(), array_flip(['pago_movil', 'transferencia']));
    }

    public function paymentMethodLabel(): string
    {
        return self::paymentMethods()[$this->paymentMethod]['label'] ?? $this->paymentMethod;
    }
}
