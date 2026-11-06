<?php

namespace App\Filament\Resources\SupplierReturns\Support;

use App\Models\SupplierReturn;
use App\Models\SupplierReturnAttachment;
use App\Models\SupplierReturnItem;
use App\Services\Purchases\SupplierReturnWorkflow;

/**
 * Traduce entre el estado del formulario (repeater + carga múltiple) y lo que recibe
 * {@see SupplierReturnWorkflow}.
 */
final class SupplierReturnFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{header: array<string, mixed>, items: list<array<string, mixed>>, attachments: array<string, string>}
     */
    public static function split(array $data): array
    {
        $paths = array_values(array_filter((array) ($data['attachments'] ?? []), fn (mixed $path): bool => is_string($path) && $path !== ''));
        $names = (array) ($data['attachment_names'] ?? []);

        $attachments = [];
        foreach ($paths as $path) {
            $attachments[$path] = (string) ($names[$path] ?? basename($path));
        }

        return [
            'header' => [
                'branch_id' => $data['branch_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_id' => filled($data['purchase_id'] ?? null) ? $data['purchase_id'] : null,
                'received_on' => $data['received_on'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            'items' => array_values((array) ($data['items'] ?? [])),
            'attachments' => $attachments,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillFromRecord(SupplierReturn $record, array $data): array
    {
        $registration = $record->attachments()->where('stage', SupplierReturnAttachment::STAGE_REGISTRATION)->get();

        $data['items'] = $record->items()->get()->map(fn (SupplierReturnItem $item): array => [
            'product_id' => $item->product_id,
            'purchase_item_id' => $item->purchase_item_id,
            'barcode' => $item->barcode,
            'product_name' => $item->product_name,
            'quantity_invoiced' => (float) $item->quantity_invoiced,
            'quantity_received' => (float) $item->quantity_received,
        ])->all();

        $data['attachments'] = $registration->pluck('file_path')->all();
        $data['attachment_names'] = $registration->pluck('original_name', 'file_path')->all();

        return $data;
    }
}
