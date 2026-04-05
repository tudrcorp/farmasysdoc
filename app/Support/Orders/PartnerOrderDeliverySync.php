<?php

namespace App\Support\Orders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderFulfillmentType;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Crea o actualiza una fila en `deliveries` cuando un pedido de aliado tiene tipo de entrega «delivery».
 */
final class PartnerOrderDeliverySync
{
    public const DELIVERY_TYPE_PARTNER = 'partner_delivery';

    public static function sync(Order $order, ?Authenticatable $actor = null): void
    {
        if ($order->id === null) {
            return;
        }

        if ($order->partner_company_id === null) {
            self::removePartnerDelivery($order);

            return;
        }

        if ($order->partner_fulfillment_type !== OrderFulfillmentType::Delivery) {
            self::removePartnerDelivery($order);

            return;
        }

        $order->loadCount('items');

        $userId = null;
        if ($actor instanceof User) {
            $userId = (int) $actor->getAuthIdentifier();
        } elseif (auth()->check() && auth()->user() instanceof User) {
            $userId = (int) auth()->id();
        }

        $delivery = Delivery::query()->firstOrNew([
            'order_id' => $order->id,
            'delivery_type' => self::DELIVERY_TYPE_PARTNER,
        ]);

        $isNew = ! $delivery->exists;

        $delivery->fill([
            'branch_id' => $order->branch_id,
            'order_number' => $order->order_number,
            'user_id' => $userId,
            'taken_by' => filled($order->delivery_assignee) ? (string) $order->delivery_assignee : null,
            'order_snapshot' => self::buildOrderSnapshot($order),
        ]);

        if ($isNew) {
            $delivery->status = DeliveryStatus::Pending;
        }

        $delivery->save();
    }

    public static function removePartnerDelivery(Order $order): void
    {
        if ($order->id === null) {
            return;
        }

        Delivery::query()
            ->where('order_id', $order->id)
            ->where('delivery_type', self::DELIVERY_TYPE_PARTNER)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildOrderSnapshot(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'partner_company_id' => $order->partner_company_id,
            'partner_company_code' => $order->partner_company_code,
            'delivery_recipient_name' => $order->delivery_recipient_name,
            'delivery_phone' => $order->delivery_phone,
            'delivery_recipient_document' => $order->delivery_recipient_document,
            'delivery_address' => $order->delivery_address,
            'delivery_city' => $order->delivery_city,
            'delivery_state' => $order->delivery_state,
            'delivery_notes' => $order->delivery_notes,
            'scheduled_delivery_at' => $order->scheduled_delivery_at?->toIso8601String(),
            'total' => $order->total !== null ? (string) $order->total : null,
            'items_count' => $order->items_count ?? $order->items()->count(),
            'is_wholesale' => (bool) $order->is_wholesale,
            'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
        ];
    }
}
