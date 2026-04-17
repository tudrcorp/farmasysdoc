<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Support\Orders\PartnerOrderDeliverySync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DeliveryPendingOrdersController extends Controller
{
    /**
     * Pedidos pendientes con entrega en `deliveries` + traslados de inventario pendientes.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isDeliveryUser()) {
            return response()->json(['message' => __('No autorizado.')], 403);
        }

        $types = PartnerOrderDeliverySync::appRoutableDeliveryTypes();

        $orders = Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereHas('deliveries', static function ($query) use ($types): void {
                $query
                    ->whereIn('delivery_type', $types)
                    ->where('status', DeliveryStatus::Pending);
            })
            ->with([
                'client:id,name,phone',
                'partnerCompany:id,trade_name,legal_name,code',
            ])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        /** @var Collection<int, Collection<int, OrderItem>> $itemsByOrderId */
        $itemsByOrderId = collect();
        if ($orders->isNotEmpty()) {
            $orderIds = $orders->modelKeys();
            $itemsByOrderId = OrderItem::query()
                ->whereIn('order_id', $orderIds)
                ->orderBy('order_id')
                ->orderBy('id')
                ->get()
                ->groupBy('order_id');
        }

        $orderJobs = $orders->map(static function (Order $order) use ($itemsByOrderId): array {
            $partner = $order->partnerCompany;
            $partnerName = null;
            if ($partner !== null) {
                $partnerName = filled($partner->trade_name)
                    ? (string) $partner->trade_name
                    : (string) $partner->legal_name;
            }

            $client = $order->client;

            $lines = $itemsByOrderId
                ->get($order->id, collect())
                ->take(10)
                ->map(static function (OrderItem $item): array {
                    $name = $item->product_name_snapshot;
                    if (! filled($name)) {
                        $name = __('Producto');
                    }

                    return [
                        'product_name' => (string) $name,
                        'quantity' => (string) $item->quantity,
                    ];
                })
                ->values()
                ->all();

            $source = $order->partner_company_id !== null ? 'aliado' : 'cliente';

            return [
                'kind' => 'order',
                'job_stage' => 'pendiente',
                'id' => $order->id,
                'sort_at' => $order->created_at?->toIso8601String(),
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'source' => $source,
                'source_label' => $source === 'aliado' ? ($partnerName ?? 'Aliado') : ($client?->name ?? 'Cliente'),
                'items_count' => (int) $order->items_count,
                'created_at' => $order->created_at?->toIso8601String(),
                'lines' => $lines,
                'partner' => $partner === null ? null : [
                    'code' => $partner->code,
                    'name' => $partnerName,
                ],
                'client' => $client === null ? null : [
                    'name' => $client->name,
                    'phone' => $client->phone,
                ],
                'delivery' => [
                    'recipient_name' => $order->delivery_recipient_name,
                    'phone' => $order->delivery_phone,
                    'address' => $order->delivery_address,
                    'city' => $order->delivery_city,
                    'state' => $order->delivery_state,
                    'notes' => $order->delivery_notes,
                    'latitude' => $order->delivery_latitude !== null ? (float) $order->delivery_latitude : null,
                    'longitude' => $order->delivery_longitude !== null ? (float) $order->delivery_longitude : null,
                ],
            ];
        });

        $transfers = ProductTransfer::query()
            ->where('status', ProductTransferStatus::Pending)
            ->with([
                'fromBranch:id,name,address,city,state',
                'toBranch:id,name,address,city,state',
                'items.product:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $transferJobs = $transfers->map(static function (ProductTransfer $transfer): array {
            $lines = $transfer->items
                ->take(10)
                ->map(static function ($item): array {
                    $name = $item->relationLoaded('product') && $item->product !== null
                        ? (string) $item->product->name
                        : __('Producto');

                    return [
                        'product_name' => $name,
                        'quantity' => (string) $item->quantity,
                    ];
                })
                ->values()
                ->all();

            $from = $transfer->fromBranch;
            $to = $transfer->toBranch;

            return [
                'kind' => 'transfer',
                'job_stage' => 'pendiente',
                'id' => $transfer->id,
                'sort_at' => $transfer->created_at?->toIso8601String(),
                'code' => $transfer->code,
                'order_number' => $transfer->code,
                'status' => ProductTransferStatus::Pending->value,
                'status_label' => ProductTransferStatus::Pending->label(),
                'source' => 'traslado',
                'source_label' => 'Traslado',
                'items_count' => $transfer->items->count(),
                'created_at' => $transfer->created_at?->toIso8601String(),
                'lines' => $lines,
                'partner' => null,
                'client' => null,
                'from_branch' => $from === null ? null : [
                    'name' => $from->name,
                    'address' => $from->address,
                    'city' => $from->city,
                    'state' => $from->state,
                ],
                'to_branch' => $to === null ? null : [
                    'name' => $to->name,
                    'address' => $to->address,
                    'city' => $to->city,
                    'state' => $to->state,
                ],
                'delivery' => [
                    'recipient_name' => $to?->name,
                    'phone' => null,
                    'address' => $to?->address,
                    'city' => $to?->city,
                    'state' => $to?->state,
                    'notes' => __('Entrega en sucursal destino.'),
                    'latitude' => null,
                    'longitude' => null,
                ],
            ];
        });

        $merged = $orderJobs->concat($transferJobs)->sortByDesc(static function (array $row): string {
            return (string) ($row['sort_at'] ?? '');
        })->values()->all();

        return response()->json([
            'data' => $merged,
        ]);
    }
}
