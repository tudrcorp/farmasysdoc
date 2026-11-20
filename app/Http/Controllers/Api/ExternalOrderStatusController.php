<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShowExternalOrderRequest;
use App\Models\Order;
use App\Models\OrderAcceptance;
use App\Models\OrderAcceptanceItem;
use App\Models\PartnerCompany;
use App\Support\Orders\ExternalPartnerOrderFinder;
use Illuminate\Http\JsonResponse;

class ExternalOrderStatusController extends Controller
{
    public function __invoke(ShowExternalOrderRequest $request, string $order, ExternalPartnerOrderFinder $finder): JsonResponse
    {
        /** @var string $partnerCode */
        $partnerCode = $request->validated('partner_company');

        $partner = $finder->partner($partnerCode);

        if ($partner === null) {
            return response()->json([
                'message' => 'La compañía aliada no existe o el código no coincide con un registro en el sistema.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $record = $finder->order($partner, $order);

        if ($record === null) {
            return response()->json([
                'message' => 'No se encontró un pedido de ese aliado con ese identificador.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $record->loadCount('items');
        $record->loadMissing('acceptance.items');

        $status = $record->status;

        return response()->json([
            'data' => [
                'order_id' => $record->id,
                'order_number' => $record->order_number,
                'partner_company' => $partner->code,
                'status' => $status instanceof OrderStatus ? $status->value : $status,
                'status_label' => $status instanceof OrderStatus ? $status->label() : null,
                'items_count' => $record->items_count,
                'subtotal' => (float) $record->subtotal,
                'tax_total' => (float) $record->tax_total,
                'discount_total' => (float) $record->discount_total,
                'total' => (float) $record->total,
                'updated_at' => $record->updated_at?->toIso8601String(),
                'acceptance' => $this->acceptancePayload($record, $partner),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function acceptancePayload(Order $order, PartnerCompany $partner): ?array
    {
        $acceptance = $order->acceptance;

        if (! $acceptance instanceof OrderAcceptance) {
            return null;
        }

        return [
            'delivery_note_number' => $acceptance->delivery_note_number,
            'accepted_at' => $acceptance->accepted_at?->toIso8601String(),
            'accepted_by' => $acceptance->accepted_by_name,
            'bcv_ves_per_usd' => (float) $acceptance->bcv_ves_per_usd,
            'accepted_total_usd' => (float) $acceptance->accepted_total_usd,
            'accepted_total_ves' => (float) $acceptance->accepted_total_ves,
            'delivery_note_url' => route('api.external.orders.delivery-note', [
                'order' => $order->order_number,
                'partner_company' => $partner->code,
            ]),
            'shipment_authorized_at' => $acceptance->shipment_authorized_at?->toIso8601String(),
            'shipment_authorized_by' => $acceptance->shipment_authorized_by_name,
            'items' => $acceptance->items->map(fn (OrderAcceptanceItem $item): array => [
                'product_name' => $item->product_name,
                'barcode' => $item->barcode,
                'sku' => $item->sku,
                'quantity_ordered' => (float) $item->quantity_ordered,
                'quantity_on_hand' => (float) $item->quantity_on_hand,
                'accepted' => (bool) $item->accepted,
                'rejection_note' => $item->accepted ? null : $item->rejection_note,
                'unit_price_usd' => (float) $item->unit_price_usd,
                'line_total_usd' => (float) $item->line_total_usd,
                'line_total_ves' => (float) $item->line_total_ves,
            ])->values()->all(),
        ];
    }
}
