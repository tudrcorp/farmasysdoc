<?php

namespace App\Http\Controllers\Reports;

use App\Filament\Clusters\Reporting\ReportingCluster;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Reports\FinancialReportBuilder;
use App\Services\Reports\FinancialReportDeliverer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class FinancialReportDownloadController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! ReportingCluster::canAccess()) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'until' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'token' => ['nullable', 'uuid'],
        ]);

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        $report = app(FinancialReportBuilder::class)->build(
            $user,
            (string) $validated['from'],
            (string) $validated['until'],
            $branchId,
        );

        return app(FinancialReportDeliverer::class)->downloadResponse(
            $user,
            $report,
            $validated['token'] ?? null,
        );
    }
}
