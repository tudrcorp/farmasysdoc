<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Enums\ProductTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryTransferDetailController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isDeliveryUser()) {
            return response()->json(['message' => __('No autorizado.')], 403);
        }

        if (! ctype_digit($id)) {
            return response()->json(['message' => __('Traslado no encontrado.')], 404);
        }

        $userId = (int) $user->getAuthIdentifier();

        /** @var ProductTransfer|null $transfer */
        $transfer = ProductTransfer::query()
            ->whereKey((int) $id)
            ->where(function ($q) use ($userId): void {
                $q->where('status', ProductTransferStatus::Pending)
                    ->orWhere(function ($q2) use ($userId): void {
                        $q2->where('status', ProductTransferStatus::InProgress)
                            ->where('delivery_user_id', $userId);
                    });
            })
            ->with([
                'fromBranch:id,name,address,city,state,phone',
                'toBranch:id,name,address,city,state,phone',
                'items.product:id,name',
            ])
            ->first();

        if ($transfer === null) {
            return response()->json(['message' => __('Traslado no encontrado.')], 404);
        }

        $items = $transfer->items->map(static function ($item): array {
            $name = $item->relationLoaded('product') && $item->product !== null
                ? (string) $item->product->name
                : __('Producto');

            return [
                'id' => $item->id,
                'product_name' => $name,
                'quantity' => (string) $item->quantity,
            ];
        });

        $from = $transfer->fromBranch;
        $to = $transfer->toBranch;

        return response()->json([
            'data' => [
                'id' => $transfer->id,
                'code' => $transfer->code,
                'status' => $transfer->status->value,
                'status_label' => $transfer->status->label(),
                'created_at' => $transfer->created_at?->toIso8601String(),
                'from_branch' => $from === null ? null : [
                    'name' => $from->name,
                    'address' => $from->address,
                    'city' => $from->city,
                    'state' => $from->state,
                    'phone' => $from->phone,
                ],
                'to_branch' => $to === null ? null : [
                    'name' => $to->name,
                    'address' => $to->address,
                    'city' => $to->city,
                    'state' => $to->state,
                    'phone' => $to->phone,
                ],
                'items' => $items,
            ],
        ]);
    }
}
