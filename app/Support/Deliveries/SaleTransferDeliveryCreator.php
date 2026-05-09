<?php

namespace App\Support\Deliveries;

use App\Enums\DeliveryStatus;
use App\Models\Branch;
use App\Models\Delivery;
use App\Models\ProductTransfer;
use Illuminate\Support\Facades\Auth;

/**
 * Registra una fila en {@see Delivery} cuando se crea un traslado de venta, para el equipo de entregas y el listado en Filament.
 */
final class SaleTransferDeliveryCreator
{
    public static function syncFromTransfer(ProductTransfer $transfer): void
    {
        if ($transfer->transfer_type !== 'sale_transfer') {
            return;
        }

        $transfer->loadMissing([
            'client',
            'fromBranch',
            'toBranch',
            'items.product',
        ]);

        $snapshot = self::buildSnapshot($transfer);

        Delivery::query()->updateOrCreate(
            [
                'product_transfer_id' => $transfer->id,
                'delivery_type' => DeliveryTypeLabels::TYPE_SALE_TRANSFER,
            ],
            [
                'branch_id' => $transfer->from_branch_id,
                'order_id' => null,
                'order_number' => $transfer->code,
                'user_id' => Auth::id(),
                'status' => DeliveryStatus::Pending,
                'taken_by' => null,
                'order_snapshot' => $snapshot,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildSnapshot(ProductTransfer $transfer): array
    {
        $client = $transfer->client;
        $from = $transfer->fromBranch;
        $to = $transfer->toBranch;

        $lines = $transfer->items->map(static function ($item): array {
            $name = $item->relationLoaded('product') && $item->product !== null
                ? (string) $item->product->name
                : 'Producto #'.(string) ($item->product_id ?? '');

            return [
                'product_name' => $name,
                'quantity' => (string) $item->quantity,
            ];
        })->values()->all();

        $linesSummary = collect($lines)
            ->map(static fn (array $line): string => $line['product_name'].' × '.$line['quantity'])
            ->implode("\n");

        return [
            'kind' => 'sale_transfer',
            'product_transfer_id' => $transfer->id,
            'code' => $transfer->code,
            'transfer_status' => $transfer->status->value,
            'transfer_status_label' => $transfer->status->label(),
            'customer_invoice_reference' => $transfer->customer_invoice_reference,
            'client_name' => $client?->name,
            'client_phone' => $client?->phone,
            'client_document' => $client?->document_number,
            'delivery_recipient_name' => $transfer->delivery_recipient_name,
            'delivery_phone' => $transfer->delivery_recipient_phone,
            'delivery_address' => $transfer->delivery_address,
            'delivery_notes' => $transfer->delivery_notes,
            'delivery_city' => null,
            'delivery_state' => null,
            'from_branch' => self::branchPayload($from),
            'to_branch' => self::branchPayload($to),
            'lines' => $lines,
            'lines_summary' => $linesSummary,
            'items_count' => count($lines),
            'is_wholesale' => false,
        ];
    }

    /**
     * @return array{name: string, address: string, city: string, state: string, phone: string}|null
     */
    private static function branchPayload(?Branch $branch): ?array
    {
        if ($branch === null) {
            return null;
        }

        return [
            'name' => (string) ($branch->name ?? ''),
            'address' => (string) ($branch->address ?? ''),
            'city' => (string) ($branch->city ?? ''),
            'state' => (string) ($branch->state ?? ''),
            'phone' => (string) ($branch->phone ?? ''),
        ];
    }
}
