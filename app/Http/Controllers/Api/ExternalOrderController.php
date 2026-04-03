<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ExternalOrderController extends Controller
{
    public function store(StoreExternalOrderRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $apiClient = $request->attributes->get('apiClient');
        $actor = 'api:'.($apiClient?->name ?? 'external');

        /** @var string $partnerCompanyCode */
        $partnerCompanyCode = $payload['partner_company'];
        unset($payload['partner_company']);

        /** @var array<int, array<string, mixed>> $items */
        $items = $payload['items'];
        unset($payload['items']);

        $order = DB::transaction(function () use ($payload, $items, $actor, $partnerCompanyCode): Order {
            $status = $payload['status'] ?? OrderStatus::Pending->value;
            $convenioType = $payload['convenio_type'] ?? ConvenioType::Particular->value;

            $order = Order::query()->create([
                'order_number' => $payload['order_number'] ?? $this->generateOrderNumber(),
                'client_id' => $payload['client_id'],
                'branch_id' => $payload['branch_id'] ?? null,
                'partner_company_code' => $partnerCompanyCode,
                'status' => $status,
                'convenio_type' => $convenioType,
                'convenio_partner_name' => $payload['convenio_partner_name'] ?? null,
                'convenio_reference' => $payload['convenio_reference'] ?? null,
                'convenio_notes' => $payload['convenio_notes'] ?? null,
                'delivery_recipient_name' => $payload['delivery_recipient_name'] ?? null,
                'delivery_phone' => $payload['delivery_phone'] ?? null,
                'delivery_address' => $payload['delivery_address'] ?? null,
                'delivery_city' => $payload['delivery_city'] ?? null,
                'delivery_state' => $payload['delivery_state'] ?? null,
                'delivery_notes' => $payload['delivery_notes'] ?? null,
                'scheduled_delivery_at' => $payload['scheduled_delivery_at'] ?? null,
                'dispatched_at' => $payload['dispatched_at'] ?? null,
                'delivered_at' => $payload['delivered_at'] ?? null,
                'delivery_assignee' => $payload['delivery_assignee'] ?? null,
                'subtotal' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => 0,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;
            $discountTotal = 0.0;

            foreach ($items as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $discount = (float) ($item['discount_amount'] ?? 0);

                $lineSubtotal = max(0, ($quantity * $unitPrice) - $discount);
                $taxAmount = 0.0;
                $lineTotal = round($lineSubtotal + $taxAmount, 2);

                $product = Product::query()->find($item['product_id']);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'inventory_id' => $item['inventory_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'line_subtotal' => $lineSubtotal,
                    'tax_amount' => $taxAmount,
                    'line_total' => $lineTotal,
                    'product_name_snapshot' => $item['product_name_snapshot'] ?? $product?->name,
                    'sku_snapshot' => $item['sku_snapshot'] ?? $product?->sku,
                ]);

                $subtotal += $lineSubtotal;
                $taxTotal += $taxAmount;
                $discountTotal += $discount;
            }

            $order->update([
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($taxTotal, 2),
                'discount_total' => round($discountTotal, 2),
                'total' => round($subtotal + $taxTotal, 2),
            ]);

            return $order->load('items');
        });

        return response()->json([
            'message' => 'Pedido creado correctamente.',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'partner_company' => $partnerCompanyCode,
                'status' => $order->status?->value ?? $order->status,
                'items_count' => $order->items->count(),
                'subtotal' => (float) $order->subtotal,
                'tax_total' => (float) $order->tax_total,
                'discount_total' => (float) $order->discount_total,
                'total' => (float) $order->total,
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'EXT-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
