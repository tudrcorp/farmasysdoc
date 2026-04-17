<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Orders\PartnerOrderDeliverySync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryOrderDetailController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isDeliveryUser()) {
            return response()->json(['message' => __('No autorizado.')], 403);
        }

        if (! ctype_digit($id)) {
            return response()->json(['message' => __('Pedido no encontrado.')], 404);
        }

        $types = PartnerOrderDeliverySync::appRoutableDeliveryTypes();
        $userId = (int) $user->getAuthIdentifier();

        /** @var Order|null $order */
        $order = Order::query()
            ->whereKey((int) $id)
            ->where(function ($outer) use ($types, $userId): void {
                $outer
                    ->where(function ($q) use ($types): void {
                        $q->where('status', OrderStatus::Pending)
                            ->whereHas('deliveries', static function ($query) use ($types): void {
                                $query
                                    ->whereIn('delivery_type', $types)
                                    ->where('status', DeliveryStatus::Pending);
                            });
                    })
                    ->orWhere(function ($q) use ($types, $userId): void {
                        $q->where('status', OrderStatus::InProgress)
                            ->whereHas('deliveries', static function ($query) use ($types, $userId): void {
                                $query
                                    ->whereIn('delivery_type', $types)
                                    ->where('status', DeliveryStatus::InProgress)
                                    ->where('user_id', $userId);
                            });
                    });
            })
            ->with([
                'client:id,name,phone,email,address,city,state',
                'partnerCompany:id,trade_name,legal_name,code,phone',
            ])
            ->with(['items' => static function ($query): void {
                $query->orderBy('id');
            }, 'items.product:id,name'])
            ->first();

        if ($order === null) {
            return response()->json(['message' => __('Pedido no encontrado.')], 404);
        }

        $partner = $order->partnerCompany;
        $partnerName = null;
        if ($partner !== null) {
            $partnerName = filled($partner->trade_name)
                ? (string) $partner->trade_name
                : (string) $partner->legal_name;
        }

        $client = $order->client;

        $items = $order->items->map(static function (OrderItem $item): array {
            $name = $item->product_name_snapshot;
            if (! filled($name) && $item->relationLoaded('product') && $item->product !== null) {
                $name = $item->product->name;
            }
            if (! filled($name)) {
                $name = __('Producto');
            }

            return [
                'id' => $item->id,
                'product_name' => (string) $name,
                'sku' => $item->sku_snapshot,
                'quantity' => (string) $item->quantity,
            ];
        });

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'notes' => $order->notes,
                'created_at' => $order->created_at?->toIso8601String(),
                'scheduled_delivery_at' => $order->scheduled_delivery_at?->toIso8601String(),
                'partner' => $partner === null ? null : [
                    'code' => $partner->code,
                    'name' => $partnerName,
                    'phone' => $partner->phone,
                ],
                'client' => $client === null ? null : [
                    'name' => $client->name,
                    'phone' => $client->phone,
                    'email' => $client->email,
                    'address' => $client->address,
                    'city' => $client->city,
                    'state' => $client->state,
                ],
                'delivery' => [
                    'recipient_name' => $order->delivery_recipient_name,
                    'phone' => $order->delivery_phone,
                    'recipient_document' => $order->delivery_recipient_document,
                    'address' => $order->delivery_address,
                    'city' => $order->delivery_city,
                    'state' => $order->delivery_state,
                    'notes' => $order->delivery_notes,
                    'latitude' => $order->delivery_latitude !== null ? (float) $order->delivery_latitude : null,
                    'longitude' => $order->delivery_longitude !== null ? (float) $order->delivery_longitude : null,
                ],
                'items' => $items,
            ],
        ]);
    }
}
