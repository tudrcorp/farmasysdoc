<?php

namespace App\Services\Hr;

use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class PayrollPeriodReportBuilder
{
    /**
     * @return array{
     *     period: PayrollPeriod,
     *     lines: Collection<int, PayrollLine>,
     *     totals: array<string, float>,
     *     generated_at: string,
     *     generated_by: string,
     *     pdf_logo_data_uri: ?string,
     * }
     */
    public function build(PayrollPeriod $period): array
    {
        $lines = $period->lines()
            ->with(['employee.branch'])
            ->orderBy('id')
            ->get();

        $totals = [
            'base_salary_usd' => round((float) $lines->sum('base_salary_usd'), 2),
            'assignments_usd' => round((float) $lines->sum('assignments_usd'), 2),
            'deductions_usd' => round((float) $lines->sum('deductions_usd'), 2),
            'loans_usd' => round((float) $lines->sum('loans_usd'), 2),
            'cash_paid_usd' => round((float) $lines->sum('cash_paid_usd'), 2),
            'cash_paid_ves' => round((float) $lines->sum('cash_paid_ves'), 2),
            'net_usd' => round((float) $lines->sum('net_usd'), 2),
            'net_ves' => round((float) $lines->sum('net_ves'), 2),
            'employees' => $lines->count(),
        ];

        $authUser = Auth::user();
        $generatedBy = $authUser instanceof User
            ? ($authUser->name ?: ($authUser->email ?? 'usuario'))
            : 'sistema';

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $pdfLogoDataUri = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return [
            'period' => $period,
            'lines' => $lines,
            'totals' => $totals,
            'generated_at' => now()->format('d/m/Y H:i'),
            'generated_by' => $generatedBy,
            'pdf_logo_data_uri' => $pdfLogoDataUri,
        ];
    }
}
