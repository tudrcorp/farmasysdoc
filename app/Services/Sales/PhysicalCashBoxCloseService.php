<?php

namespace App\Services\Sales;

use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxCloseReport;
use App\Models\User;
use App\Support\Cash\NotifyAdministratorsOnPhysicalCashBoxClose;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PhysicalCashBoxCloseService
{
    public function __construct(
        private readonly PhysicalCashBoxShiftReportBuilder $shiftReportBuilder,
        private readonly PhysicalCashBoxShiftPaymentTotalsPdfGenerator $paymentTotalsPdfGenerator,
        private readonly PhysicalCashBoxCloseReportRecorder $closeReportRecorder,
        private readonly NotifyAdministratorsOnPhysicalCashBoxClose $closeNotifier,
    ) {}

    /**
     * @param  list<array{bank_code: string, amount_ves: float}>  $posDeclarations
     */
    public function close(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        float $declaredUsd,
        float $declaredVes,
        string $usdCashPhotoPath,
        string $posReceiptPhotoPath,
        array $posDeclarations,
    ): PhysicalCashBoxCloseReport {
        $expectedUsd = round((float) $physicalCashBox->amount_usd, 2);
        $expectedVes = round((float) $physicalCashBox->amount_ves, 2);
        $openedAt = $physicalCashBox->opened_at ?? now();
        $closedAt = now();

        DB::transaction(function () use ($physicalCashBox, $declaredUsd, $declaredVes, $usdCashPhotoPath, $posReceiptPhotoPath, $closedAt): void {
            $physicalCashBox->forceFill([
                'amount_usd' => $declaredUsd,
                'amount_ves' => $declaredVes,
                'is_open' => false,
                'closed_at' => $closedAt,
                'close_usd_cash_photo_path' => $usdCashPhotoPath,
                'close_pos_receipt_photo_path' => $posReceiptPhotoPath,
            ])->save();
        });

        $physicalCashBox->refresh();

        $reconciliationSnapshot = [
            'expected_usd' => $expectedUsd,
            'expected_ves' => $expectedVes,
            'declared_usd' => $declaredUsd,
            'declared_ves' => $declaredVes,
            'declared_pos_lines' => $posDeclarations,
        ];

        $report = $this->shiftReportBuilder->build(
            $cashier,
            $physicalCashBox,
            $openedAt,
            $closedAt,
            $reconciliationSnapshot,
        );

        $pdfBytes = $this->generatePdfSafely($report);
        $closeReport = $this->closeReportRecorder->record(
            $cashier,
            $physicalCashBox,
            $openedAt,
            $closedAt,
            $report,
            $usdCashPhotoPath,
            $posReceiptPhotoPath,
            $pdfBytes,
        );

        $this->notifySafely(
            $cashier,
            $physicalCashBox,
            $openedAt,
            $closedAt,
            $reconciliationSnapshot,
            $closeReport,
            $report,
        );

        return $closeReport->fresh() ?? $closeReport;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function generatePdfSafely(array $report): string
    {
        try {
            return $this->paymentTotalsPdfGenerator->generate($report);
        } catch (Throwable $exception) {
            Log::warning('Cierre de caja física: no se pudo generar el PDF del reporte', [
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @param  array<string, mixed>  $reconciliationSnapshot
     * @param  array<string, mixed>  $report
     */
    private function notifySafely(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        CarbonInterface $openedAt,
        CarbonInterface $closedAt,
        array $reconciliationSnapshot,
        PhysicalCashBoxCloseReport $closeReport,
        array $report,
    ): void {
        try {
            $this->closeNotifier->notify(
                cashier: $cashier,
                physicalCashBox: $physicalCashBox,
                openedAt: $openedAt,
                closedAt: $closedAt,
                reconciliationSnapshot: $reconciliationSnapshot,
                closeReport: $closeReport,
                preparedReport: $report,
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar las notificaciones de cierre de caja física', [
                'cashier_id' => $cashier->getKey(),
                'physical_cash_box_id' => $physicalCashBox->getKey(),
                'close_report_id' => $closeReport->getKey(),
                'error' => $exception->getMessage(),
            ]);

            $closeReport->forceFill([
                'whatsapp_error' => $closeReport->whatsapp_error ?: $exception->getMessage(),
                'email_error' => $closeReport->email_error ?: $exception->getMessage(),
            ])->save();
        }
    }
}
