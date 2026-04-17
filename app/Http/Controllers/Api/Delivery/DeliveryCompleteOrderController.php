<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryCompleteOrderRequest;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Support\Deliveries\MarkDeliveryCompletedWithEvidence;
use App\Support\Orders\PartnerOrderDeliverySync;
use App\Support\Partners\InsufficientPartnerCreditException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DeliveryCompleteOrderController extends Controller
{
    /**
     * Cierra la entrega con foto de evidencia y marca el pedido como finalizado.
     */
    public function __invoke(DeliveryCompleteOrderRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => __('No autorizado.')], 403);
        }

        if (! ctype_digit($id)) {
            return response()->json(['message' => __('Pedido no encontrado.')], 404);
        }

        $orderId = (int) $id;
        $userId = (int) $user->getAuthIdentifier();
        $types = PartnerOrderDeliverySync::appRoutableDeliveryTypes();

        /** @var Order|null $order */
        $order = Order::query()
            ->whereKey($orderId)
            ->where('status', OrderStatus::InProgress)
            ->whereHas('deliveries', static function ($query) use ($userId, $types): void {
                $query
                    ->whereIn('delivery_type', $types)
                    ->where('user_id', $userId)
                    ->where('status', DeliveryStatus::InProgress);
            })
            ->first();

        if ($order === null) {
            return response()->json(['message' => __('Pedido no encontrado o no está en ruta contigo.')], 404);
        }

        /** @var Delivery|null $delivery */
        $delivery = Delivery::query()
            ->where('order_id', $order->id)
            ->whereIn('delivery_type', $types)
            ->where('user_id', $userId)
            ->where('status', DeliveryStatus::InProgress)
            ->first();

        if ($delivery === null) {
            return response()->json(['message' => __('No se encontró la entrega activa de este pedido.')], 422);
        }

        $file = $request->file('evidence');
        if ($file === null) {
            return response()->json(['message' => __('Falta la imagen de evidencia.')], 422);
        }

        $path = $file->store('deliveries/evidence', 'public');

        try {
            MarkDeliveryCompletedWithEvidence::execute($delivery, $user, $path);
        } catch (InsufficientPartnerCreditException $e) {
            Storage::disk('public')->delete($path);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            Storage::disk('public')->delete($path);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh();

        return response()->json([
            'message' => __('Entrega registrada. Pedido finalizado.'),
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
            ],
        ]);
    }
}
