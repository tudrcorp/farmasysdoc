<?php

namespace App\Support\Deliveries;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cierra una entrega «En proceso» con foto de evidencia y, si hay pedido vinculado, lo marca Finalizado.
 */
final class MarkDeliveryCompletedWithEvidence
{
    /**
     * @throws InvalidArgumentException
     */
    public static function execute(Delivery $delivery, User $user, string $evidencePath): void
    {
        $evidencePath = trim($evidencePath);
        if ($evidencePath === '') {
            throw new InvalidArgumentException('Debe adjuntar una imagen de evidencia de la entrega.');
        }

        if ($delivery->status !== DeliveryStatus::InProgress) {
            throw new InvalidArgumentException('Solo las entregas en proceso pueden cerrarse con evidencia.');
        }

        $delivery->loadMissing('order');

        if ($delivery->order_id !== null && $delivery->order === null) {
            throw new InvalidArgumentException('El pedido vinculado no existe.');
        }

        $order = $delivery->order;

        if ($order !== null && $order->status === OrderStatus::Completed) {
            throw new InvalidArgumentException('El pedido ya está finalizado.');
        }

        $actorLabel = MarkDeliveryInProgress::assigneeDisplayName($user);

        DB::transaction(function () use ($delivery, $order, $evidencePath, $actorLabel): void {
            $delivery->forceFill([
                'delivery_evidence_path' => $evidencePath,
                'status' => DeliveryStatus::Completed,
            ])->save();

            if ($order !== null) {
                $deliveredAt = $order->delivered_at ?? now();
                $duration = Order::computeFulfillmentDurationMinutes($order->created_at, $deliveredAt);

                $order->forceFill([
                    'status' => OrderStatus::Completed,
                    'delivered_at' => $deliveredAt,
                    'updated_by' => $actorLabel,
                    'delivery_fulfillment_duration_minutes' => $duration,
                ])->save();
            }
        });
    }
}
