<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShowExternalOrderRequest;
use App\Models\Order;
use App\Models\PartnerCompany;
use Illuminate\Http\JsonResponse;

class ExternalOrderStatusController extends Controller
{
    public function __invoke(ShowExternalOrderRequest $request, string $order): JsonResponse
    {
        /** @var string $partnerCode */
        $partnerCode = $request->validated('partner_company');

        $partner = PartnerCompany::query()
            ->where('code', $partnerCode)
            ->first();

        if ($partner === null) {
            return response()->json([
                'message' => 'La compañía aliada no existe o el código no coincide con un registro en el sistema.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $scopedOrders = Order::query()
            ->where(function ($query) use ($partner): void {
                $query->where('partner_company_code', $partner->code)
                    ->orWhere('partner_company_id', $partner->id);
            });

        $record = (clone $scopedOrders)->where('order_number', $order)->first();

        if ($record === null && ctype_digit($order)) {
            $record = (clone $scopedOrders)->whereKey((int) $order)->first();
        }

        if ($record === null) {
            return response()->json([
                'message' => 'No se encontró un pedido de ese aliado con ese identificador.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $record->loadCount('items');

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
            ],
        ]);
    }
}
