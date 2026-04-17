<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Support\Deliveries\MarkDeliveryInProgress;
use App\Support\Orders\PartnerOrderDeliverySync;
use App\Support\Partners\InsufficientPartnerCreditException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeliveryTakeOrderController extends Controller
{
    /**
     * Asigna el pedido al repartidor (pasa a «en proceso») si hay una entrega pendiente en `deliveries`.
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isDeliveryUser()) {
            return response()->json(['message' => __('No autorizado.')], 403);
        }

        if (! ctype_digit($id)) {
            return response()->json(['message' => __('Pedido no encontrado.')], 404);
        }

        $orderId = (int) $id;

        try {
            $payload = DB::transaction(function () use ($orderId, $user): array {
                /** @var Order|null $order */
                $order = Order::query()
                    ->whereKey($orderId)
                    ->where('status', OrderStatus::Pending)
                    ->lockForUpdate()
                    ->first();

                if ($order === null) {
                    return ['error' => 'not_found'];
                }

                /** @var Delivery|null $delivery */
                $delivery = Delivery::query()
                    ->where('order_id', $order->id)
                    ->whereIn('delivery_type', PartnerOrderDeliverySync::appRoutableDeliveryTypes())
                    ->lockForUpdate()
                    ->first();

                if ($delivery === null) {
                    return ['error' => 'no_delivery'];
                }

                $userId = (int) $user->getAuthIdentifier();

                if ($delivery->status === DeliveryStatus::InProgress && (int) $delivery->user_id === $userId) {
                    $order->refresh();

                    return [
                        'ok' => true,
                        'idempotent' => true,
                        'order' => $this->orderSummary($order),
                    ];
                }

                if ($delivery->user_id !== null && (int) $delivery->user_id !== $userId) {
                    return ['error' => 'taken'];
                }

                if ($delivery->status !== DeliveryStatus::Pending) {
                    return ['error' => 'not_pending'];
                }

                MarkDeliveryInProgress::execute($delivery, $user);

                $order->refresh();

                return [
                    'ok' => true,
                    'idempotent' => false,
                    'order' => $this->orderSummary($order),
                ];
            });
        } catch (InsufficientPartnerCreditException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (($payload['error'] ?? null) === 'not_found') {
            return response()->json(['message' => __('Pedido no disponible o ya no está pendiente.')], 404);
        }

        if (($payload['error'] ?? null) === 'no_delivery') {
            return response()->json(['message' => __('Este pedido no tiene entrega a domicilio registrada.')], 422);
        }

        if (($payload['error'] ?? null) === 'taken') {
            return response()->json(['message' => __('Otro repartidor ya tomó este pedido.')], 409);
        }

        if (($payload['error'] ?? null) === 'not_pending') {
            return response()->json(['message' => __('Esta entrega ya no está pendiente.')], 409);
        }

        return response()->json([
            'message' => __('Pedido asignado. Buen reparto.'),
            'data' => $payload['order'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSummary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
        ];
    }
}
