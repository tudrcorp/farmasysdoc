<?php

namespace App\Http\Controllers;

use App\Filament\Resources\FiscalPrinters\FiscalPrinterResource;
use App\Services\Audit\AuditLogger;
use App\Support\Fiscal\FiscalAgentPackage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga del instalador del agente Windows de máquina fiscal (solo usuarios con acceso a Máquinas fiscales).
 */
final class FiscalAgentDownloadController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        abort_unless(FiscalPrinterResource::canViewAny(), 403);

        $package = FiscalAgentPackage::current();
        abort_if($package === null, 404, 'Aún no se ha subido el paquete del agente.');

        AuditLogger::record(
            'fiscal_agent_downloaded',
            'Fiscal · Descarga del agente Windows '.$package['version'],
            properties: ['module' => 'fiscal', 'version' => $package['version']],
        );

        return Storage::disk(FiscalAgentPackage::disk())->download(
            FiscalAgentPackage::path(),
            $package['filename'],
            ['Content-Type' => 'application/zip'],
        );
    }
}
