<?php

namespace App\Support\Deliveries;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pasa una entrega de «Pendiente» a «En proceso» y alinea el pedido vinculado para visibilidad del aliado.
 */
final class MarkDeliveryInProgress
{
    public static function assigneeDisplayName(User $user): string
    {
        $name = trim((string) $user->name);

        return $name !== '' ? $name : (string) $user->email;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function execute(Delivery $delivery, User $user): void
    {
        if ($delivery->status !== DeliveryStatus::Pending) {
            throw new InvalidArgumentException('Solo las entregas pendientes pueden iniciarse.');
        }

        $delivery->loadMissing('order');

        if ($delivery->order_id === null || $delivery->order === null) {
            throw new InvalidArgumentException('Esta entrega no tiene un pedido vinculado.');
        }

        $order = $delivery->order;

        if ($order->status === OrderStatus::Completed) {
            throw new InvalidArgumentException('El pedido ya está finalizado; no se puede poner en proceso.');
        }

        $name = self::assigneeDisplayName($user);

        DB::transaction(function () use ($delivery, $order, $user, $name): void {
            $order->forceFill([
                'status' => OrderStatus::InProgress,
                'delivery_assignee' => $name,
                'updated_by' => $name,
            ])->save();

            $delivery->refresh();

            $delivery->forceFill([
                'status' => DeliveryStatus::InProgress,
                'taken_by' => $name,
                'user_id' => (int) $user->getAuthIdentifier(),
            ])->save();
        });
    }
}
