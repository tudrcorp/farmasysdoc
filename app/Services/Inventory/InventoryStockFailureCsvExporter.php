<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockFailure;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryStockFailureCsvExporter
{
    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
            'ID',
            'Fecha registro',
            'Sucursal',
            'Producto',
            'Código producto',
            'Existencia',
            'Cajero',
            'Email cajero',
        ];
    }

    /**
     * @param  Builder<InventoryStockFailure>  $query
     */
    public function stream(Builder $query): StreamedResponse
    {
        $fileName = 'fallas-existencia-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $this->headers(), ';');

            (clone $query)
                ->with(['branch:id,name', 'user:id,name,email'])
                ->chunkById(500, function ($records) use ($stream): void {
                    foreach ($records as $record) {
                        if (! $record instanceof InventoryStockFailure) {
                            continue;
                        }

                        fputcsv($stream, [
                            $record->getKey(),
                            $record->created_at?->format('d/m/Y H:i:s') ?? '',
                            (string) ($record->branch?->name ?? ''),
                            (string) $record->product_name,
                            (string) $record->product_code,
                            number_format((float) $record->quantity, 3, ',', ''),
                            (string) ($record->user?->name ?? ''),
                            (string) ($record->user?->email ?? ''),
                        ], ';');
                    }
                });

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
