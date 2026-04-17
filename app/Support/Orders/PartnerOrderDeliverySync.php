<?php

namespace App\Support\Orders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderFulfillmentType;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Crea o actualiza filas en `deliveries` para pedidos con entrega a domicilio (aliado o cliente/sucursal).
 */
final class PartnerOrderDeliverySync
{
    public const DELIVERY_TYPE_PARTNER = 'partner_delivery';

    public const DELIVERY_TYPE_CLIENT_ORDER = 'client_order_delivery';

    public static function sync(Order $order, ?Authenticatable $actor = null): void
    {
        if ($order->id === null) {
            return;
        }

        if ($order->partner_company_id !== null) {
            self::removeClientOrderDelivery($order);

            if ($order->partner_fulfillment_type !== OrderFulfillmentType::Delivery) {
                self::removePartnerDelivery($order);

                return;
            }

            $order->loadCount('items');

            $delivery = Delivery::query()->firstOrNew([
                'order_id' => $order->id,
                'delivery_type' => self::DELIVERY_TYPE_PARTNER,
            ]);

            $isNew = ! $delivery->exists;

            $delivery->fill([
                'branch_id' => $order->branch_id,
                'order_number' => $order->order_number,
                'order_snapshot' => self::buildOrderSnapshot($order),
                'taken_by' => filled($order->delivery_assignee) ? (string) $order->delivery_assignee : null,
            ]);

            if ($isNew) {
                $delivery->status = DeliveryStatus::Pending;
                $delivery->user_id = null;
            }

            $delivery->save();

            return;
        }

        self::removePartnerDelivery($order);

        if (! self::clientOrderShouldHaveDeliveryRow($order)) {
            self::removeClientOrderDelivery($order);

            return;
        }

        $order->loadCount('items');

        $delivery = Delivery::query()->firstOrNew([
            'order_id' => $order->id,
            'delivery_type' => self::DELIVERY_TYPE_CLIENT_ORDER,
        ]);

        $isNew = ! $delivery->exists;

        $delivery->fill([
            'branch_id' => $order->branch_id,
            'order_number' => $order->order_number,
            'order_snapshot' => self::buildOrderSnapshot($order),
            'taken_by' => filled($order->delivery_assignee) ? (string) $order->delivery_assignee : null,
        ]);

        if ($isNew) {
            $delivery->status = DeliveryStatus::Pending;
            $delivery->user_id = null;
        }

        $delivery->save();
    }

    /**
     * @return list<string>
     */
    public static function appRoutableDeliveryTypes(): array
    {
        return [
            self::DELIVERY_TYPE_PARTNER,
            self::DELIVERY_TYPE_CLIENT_ORDER,
        ];
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

    public static function removeClientOrderDelivery(Order $order): void
    {
        if ($order->id === null) {
            return;
        }

        Delivery::query()
            ->where('order_id', $order->id)
            ->where('delivery_type', self::DELIVERY_TYPE_CLIENT_ORDER)
            ->delete();
    }

    public static function removeAllSyncedAppDeliveries(Order $order): void
    {
        if ($order->id === null) {
            return;
        }

        Delivery::query()
            ->where('order_id', $order->id)
            ->whereIn('delivery_type', self::appRoutableDeliveryTypes())
            ->delete();
    }

    private static function clientOrderShouldHaveDeliveryRow(Order $order): bool
    {
        if ($order->partner_company_id !== null) {
            return false;
        }

        if (in_array($order->status, [OrderStatus::Completed], true)) {
            return false;
        }

        return filled($order->delivery_address)
            || self::orderHasCoordinates($order);
    }

    private static function orderHasCoordinates(Order $order): bool
    {
        return $order->delivery_latitude !== null && $order->delivery_longitude !== null;
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
            'delivery_latitude' => $order->delivery_latitude !== null ? (string) $order->delivery_latitude : null,
            'delivery_longitude' => $order->delivery_longitude !== null ? (string) $order->delivery_longitude : null,
            'delivery_notes' => $order->delivery_notes,
            'scheduled_delivery_at' => $order->scheduled_delivery_at?->toIso8601String(),
            'total' => $order->total !== null ? (string) $order->total : null,
            'items_count' => $order->items_count ?? $order->items()->count(),
            'is_wholesale' => (bool) $order->is_wholesale,
            'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
        ];
    }
}
