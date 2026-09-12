<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryAuditDetailedReportPdfFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class InventoryAuditDetailedReportPdfController extends Controller
{
    public function __invoke(Request $request, InventoryAuditDetailedReportPdfFactory $factory): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->isAdministrator()) {
            abort(403);
        }

        $filters = [
            'from' => $request->query('from'),
            'until' => $request->query('until'),
            'branch_id' => $request->query('branch'),
            'letter_from' => $request->query('letter_from'),
            'letter_to' => $request->query('letter_to'),
            'status' => $request->query('status'),
        ];

        try {
            $response = $factory->download($filters, $actor);
        } catch (ValidationException $exception) {
            abort(422, collect($exception->errors())->flatten()->first() ?: 'Filtros inválidos.');
        }

        AuditLogger::record(
            'inventory_audit_detailed_pdf_downloaded',
            'Auditoría de inventario · Descarga de PDF detallado',
            properties: [
                'module' => 'inventory_audits',
                'from' => $filters['from'],
                'until' => $filters['until'],
                'branch_id' => $filters['branch_id'],
                'letter_from' => $filters['letter_from'],
                'letter_to' => $filters['letter_to'],
                'status' => $filters['status'],
            ],
            user: $actor,
        );

        return $response;
    }
}
