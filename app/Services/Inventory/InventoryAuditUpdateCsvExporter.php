<?php

namespace App\Services\Inventory;

use App\Models\InventoryAuditUpdate;
use App\Support\Inventory\InventoryQuantityFormat;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryAuditUpdateCsvExporter
{
    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
            'ID',
            'Fecha',
            'Sucursal',
            'SKU',
            'Código barras',
            'Producto',
            'Existencia anterior',
            'Existencia nueva',
            'Delta existencia',
            'Costo anterior',
            'Costo nuevo',
            'Cambió existencia',
            'Cambió costo',
            'Usuario',
            'Auditoría ID',
        ];
    }

    /**
     * @param  Builder<InventoryAuditUpdate>  $query
     */
    public function stream(Builder $query): StreamedResponse
    {
        $fileName = 'auditoria-productos-actualizados-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $this->headers(), ';');

            (clone $query)
                ->orderBy('id')
                ->chunkById(500, function ($records) use ($stream): void {
                    foreach ($records as $record) {
                        if (! $record instanceof InventoryAuditUpdate) {
                            continue;
                        }

                        fputcsv($stream, [
                            $record->getKey(),
                            $record->processed_at?->format('d/m/Y H:i:s') ?? '',
                            (string) $record->branch_name,
                            (string) ($record->product_sku ?? ''),
                            (string) ($record->product_barcode ?? ''),
                            (string) $record->product_name,
                            InventoryQuantityFormat::display($record->previous_quantity),
                            InventoryQuantityFormat::display($record->new_quantity),
                            InventoryQuantityFormat::display($record->quantity_delta),
                            number_format((float) $record->previous_cost_price, 2, '.', ''),
                            $record->new_cost_price !== null
                                ? number_format((float) $record->new_cost_price, 2, '.', '')
                                : '',
                            $record->quantity_changed ? 'Sí' : 'No',
                            $record->cost_changed ? 'Sí' : 'No',
                            (string) ($record->processed_by_name ?? ''),
                            (string) $record->inventory_audit_id,
                        ], ';');
                    }
                });

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
