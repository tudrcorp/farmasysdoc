<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalServiceOrderRequest;
use App\Models\OrderService;
use App\Models\PartnerCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExternalServiceOrderController extends Controller
{
    public function store(StoreExternalServiceOrderRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $apiClient = $request->attributes->get('apiClient');
        $actor = 'api:'.($apiClient?->name ?? 'external');

        /** @var PartnerCompany $partner */
        $partner = PartnerCompany::query()
            ->where('code', $payload['partner_company'])
            ->firstOrFail();

        $items = $payload['items'];

        $order = DB::transaction(function () use ($payload, $items, $partner, $actor): OrderService {
            $order = OrderService::query()->create([
                'service_order_number' => 'ORD-TEMP-'.(string) Str::uuid(),
                'partner_company_id' => $partner->id,
                'client_id' => null,
                'branch_id' => null,
                'status' => $payload['status'],
                'priority' => $payload['priority'],
                'service_type' => $payload['service_type'],
                'authorization_reference' => null,
                'external_reference' => $payload['external_reference'],
                'patient_name' => $payload['patient_name'],
                'patient_document' => $payload['patient_document'],
                'patient_phone' => $payload['patient_phone'],
                'patient_email' => $payload['patient_email'],
                'ordered_at' => now(),
                'scheduled_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'subtotal' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => 0,
                'diagnosis' => $payload['diagnosis'],
                'notes' => null,
                'items' => $items,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            $order->update([
                'service_order_number' => OrderService::formatServiceOrderNumber($order->getKey()),
            ]);

            return $order->fresh();
        });

        return response()->json([
            'message' => 'Orden de servicio registrada correctamente.',
            'data' => [
                'order_service_id' => $order->id,
                'service_order_number' => $order->service_order_number,
                'partner_company_code' => $partner->code,
                'status' => $order->status,
                'priority' => $order->priority,
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'items_count' => is_array($order->items) ? count($order->items) : 0,
            ],
        ], Response::HTTP_CREATED);
    }
}
