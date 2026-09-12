<?php

namespace App\Services\Inventory;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class InventoryAuditDetailedReportPdfFactory
{
    public function __construct(
        private readonly InventoryAuditDetailedReportBuilder $builder,
    ) {}

    /**
     * @param  array{
     *     from?: string|null,
     *     until?: string|null,
     *     branch_id?: int|null,
     *     letter_from?: string|null,
     *     letter_to?: string|null,
     *     status?: string|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function viewData(array $filters, User $actor): array
    {
        $payload = $this->builder->build($filters, $actor);

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $payload['pdf_logo_data_uri'] = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $payload['pdf_document_ref'] = strtoupper(substr(hash(
            'sha256',
            implode('|', [
                (string) ($payload['period_from'] ?? ''),
                (string) ($payload['period_until'] ?? ''),
                (string) ($payload['branch_name'] ?? ''),
                (string) ($payload['letter_range'] ?? ''),
                (string) ($payload['status_label'] ?? ''),
                (string) ($payload['generated_at'] ?? ''),
                (string) ($payload['generated_by'] ?? ''),
                (string) ($payload['summary']['audits_count'] ?? 0),
            ]),
        ), 0, 10));

        return $payload;
    }

    /**
     * @param  array{
     *     from?: string|null,
     *     until?: string|null,
     *     branch_id?: int|null,
     *     letter_from?: string|null,
     *     letter_to?: string|null,
     *     status?: string|null
     * }  $filters
     */
    public function download(array $filters, User $actor): Response
    {
        set_time_limit(180);
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        try {
            $payload = $this->viewData($filters, $actor);
            $contents = Pdf::loadView('pdf.inventory-audit-detailed-report', $payload)
                ->setPaper('a4', 'landscape')
                ->output();
            $filename = $this->filename($payload);

            return response()->streamDownload(function () use ($contents): void {
                echo $contents;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('PDF auditoría inventario detallado: no se pudo generar', [
                'filters' => $filters,
                'actor_id' => $actor->getKey(),
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            abort(500, 'No se pudo generar el PDF. Si el período incluye muchas sucursales, filtre por sucursal o rango de letras e inténtelo de nuevo.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function filename(array $payload): string
    {
        $parts = ['auditoria-inventario-detalle'];

        if (filled($payload['period_from'] ?? null) && filled($payload['period_until'] ?? null)) {
            $from = str_replace('/', '-', (string) $payload['period_from']);
            $until = str_replace('/', '-', (string) $payload['period_until']);
            $parts[] = $from === $until ? $from : $from.'_'.$until;
        }

        return implode('-', $parts).'.pdf';
    }
}
