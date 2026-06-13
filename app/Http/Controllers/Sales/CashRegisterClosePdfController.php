<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Sales\CashRegisterCloseReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CashRegisterClosePdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $from = Carbon::parse((string) $request->query('from'))->startOfDay();
        $until = Carbon::parse((string) $request->query('until'))->endOfDay();

        if ($until->lt($from)) {
            abort(422);
        }

        $builder = app(CashRegisterCloseReportBuilder::class);
        $requestedBranchIds = $this->parseBranchIdsFromQuery($request->query('branches'));
        $branchFilter = $builder->resolveAdministratorBranchFilter($requestedBranchIds);

        if ($branchFilter === []) {
            abort(422, 'Las sucursales seleccionadas no son válidas.');
        }

        $payload = $builder->build($from, $until, $branchFilter);

        AuditLogger::record(
            'pos_caja_close_pdf_downloaded',
            'Caja · Descarga de PDF de cierre de caja',
            properties: [
                'module' => 'pos_caja',
                'period_from' => $from->toDateString(),
                'period_until' => $until->toDateString(),
                'branch_ids' => $branchFilter ?? [],
            ],
        );

        $suffix = $from->isSameDay($until)
            ? $from->format('Y-m-d')
            : $from->format('Y-m-d').'_'.$until->format('Y-m-d');
        $filename = 'cierre-caja-'.$suffix.'.pdf';

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $payload['pdf_logo_data_uri'] = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $branchFingerprint = $branchFilter === null
            ? 'all'
            : implode(',', $branchFilter);

        $payload['pdf_document_ref'] = strtoupper(substr(hash(
            'sha256',
            $from->toDateString().'|'.$until->toDateString().'|'.$branchFingerprint.'|'.($payload['generated_at'] ?? '').'|'.($payload['generated_by'] ?? '')
        ), 0, 10));

        return Pdf::loadView('pdf.cash-register-close', $payload)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * @return list<int>|null
     */
    private function parseBranchIdsFromQuery(mixed $branchesParam): ?array
    {
        if (! is_string($branchesParam) || trim($branchesParam) === '') {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (string $part): int => (int) trim($part),
            explode(',', $branchesParam),
        ), static fn (int $id): bool => $id > 0)));

        return $ids === [] ? null : $ids;
    }
}
