<?php

namespace App\Services\Sales;

use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxCloseReport;
use App\Models\User;
use App\Support\Cash\PhysicalCashBoxCloseVariance;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

final class PhysicalCashBoxCloseReportRecorder
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function record(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        CarbonInterface $openedAt,
        CarbonInterface $closedAt,
        array $report,
        string $usdCashPhotoPath,
        string $posReceiptPhotoPath,
        string $pdfBytes,
    ): PhysicalCashBoxCloseReport {
        $cash = is_array($report['cash_box_reconciliation'] ?? null)
            ? $report['cash_box_reconciliation']
            : [];
        $pos = is_array($report['pos_reconciliation'] ?? null)
            ? $report['pos_reconciliation']
            : [];

        $hasCashMismatch = (bool) ($cash['has_mismatch'] ?? false)
            || PhysicalCashBoxCloseVariance::isMismatch((float) ($cash['difference_usd'] ?? 0))
            || PhysicalCashBoxCloseVariance::isMismatch((float) ($cash['difference_ves'] ?? 0));
        $hasPosMismatch = (bool) ($pos['has_mismatch'] ?? false);

        $pdfPath = $this->storePdf($physicalCashBox, $closedAt, $pdfBytes);

        return PhysicalCashBoxCloseReport::query()->create([
            'physical_cash_box_id' => $physicalCashBox->getKey(),
            'user_id' => $cashier->getKey(),
            'branch_id' => filled($cashier->branch_id) ? (int) $cashier->branch_id : null,
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'declared_usd' => round((float) ($cash['declared_usd'] ?? 0), 2),
            'declared_ves' => round((float) ($cash['declared_ves'] ?? 0), 2),
            'expected_usd' => round((float) ($cash['expected_usd'] ?? 0), 2),
            'expected_ves' => round((float) ($cash['expected_ves'] ?? 0), 2),
            'difference_usd' => round((float) ($cash['difference_usd'] ?? 0), 2),
            'difference_ves' => round((float) ($cash['difference_ves'] ?? 0), 2),
            'pos_declared_ves' => round((float) ($pos['declared_total_ves'] ?? 0), 2),
            'pos_system_ves' => round((float) ($pos['system_total_ves'] ?? 0), 2),
            'pos_difference_ves' => round((float) ($pos['difference_ves'] ?? 0), 2),
            'has_cash_mismatch' => $hasCashMismatch,
            'has_pos_mismatch' => $hasPosMismatch,
            'has_mismatch' => $hasCashMismatch || $hasPosMismatch,
            'pos_lines' => $pos['lines'] ?? [],
            'report_snapshot' => $report,
            'pdf_path' => $pdfPath,
            'close_usd_cash_photo_path' => $usdCashPhotoPath !== '' ? $usdCashPhotoPath : null,
            'close_pos_receipt_photo_path' => $posReceiptPhotoPath !== '' ? $posReceiptPhotoPath : null,
        ]);
    }

    private function storePdf(PhysicalCashBox $physicalCashBox, CarbonInterface $closedAt, string $pdfBytes): ?string
    {
        if ($pdfBytes === '') {
            return null;
        }

        $path = 'physical-cash-box/close-reports/'.$physicalCashBox->getKey().'/'
            .$closedAt->timezone((string) config('app.timezone'))->format('Y-m-d-His')
            .'.pdf';

        Storage::disk('local')->put($path, $pdfBytes);

        return $path;
    }
}
