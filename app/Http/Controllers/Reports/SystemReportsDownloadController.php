<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\SystemReportsCsvExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SystemReportsDownloadController extends Controller
{
    private const ALLOWED_SLUGS = [
        'ventas',
        'ventas-por-usuario',
        'ventas-por-sucursal',
        'companias-aliadas',
        'ordenes-servicio',
        'pedidos',
        'traslados-por-usuario',
        'traslados-por-sucursal',
        'traslados-costos',
        'clientes',
        'catalogo-productos',
        'top-clientes-sucursal',
        'productos-mas-vendidos',
        'inventario',
        'tasas-bcv',
        'ingresos-aliados',
        'compras',
    ];

    public function download(Request $request, string $slug): StreamedResponse
    {
        if (! in_array($slug, self::ALLOWED_SLUGS, true)) {
            abort(404);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        AuditLogger::record(
            event: 'system_report_download_requested',
            description: 'Reportes: solicitud de descarga CSV.',
            properties: [
                'slug' => $slug,
                'query' => $request->query->all(),
            ],
        );

        return app(SystemReportsCsvExporter::class)->stream($user, $slug, $request);
    }
}
