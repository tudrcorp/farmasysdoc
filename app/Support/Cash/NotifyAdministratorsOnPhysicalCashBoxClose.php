<?php

namespace App\Support\Cash;

use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Services\Sales\PhysicalCashBoxShiftPaymentTotalsPdfGenerator;
use App\Services\Sales\PhysicalCashBoxShiftReportBuilder;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyAdministratorsOnPhysicalCashBoxClose
{
    public function __construct(
        private readonly PhysicalCashBoxShiftReportBuilder $shiftReportBuilder,
        private readonly PhysicalCashBoxShiftPaymentTotalsPdfGenerator $paymentTotalsPdfGenerator,
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
    ) {}

    public function notify(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        CarbonInterface $openedAt,
        CarbonInterface $closedAt,
    ): void {
        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de cierre de caja física', [
                'cashier_id' => $cashier->getKey(),
                'physical_cash_box_id' => $physicalCashBox->getKey(),
            ]);

            return;
        }

        $phones = $this->resolveAdministratorPhones();

        if ($phones === []) {
            Log::notice('Cierre de caja física: sin teléfonos de administradores para WhatsApp', [
                'cashier_id' => $cashier->getKey(),
                'physical_cash_box_id' => $physicalCashBox->getKey(),
            ]);

            return;
        }

        $report = $this->shiftReportBuilder->build($cashier, $physicalCashBox, $openedAt, $closedAt);
        $bannerImage = $this->ultramsgWhatsAppClient->resolvePhysicalCashBoxBannerImage();
        $caption = $this->buildCaption($report);
        $pdfBytes = $this->paymentTotalsPdfGenerator->generate($report);
        $pdfDocument = base64_encode($pdfBytes);
        $pdfFilename = 'totales-pago-cierre-caja-'.$closedAt->timezone((string) config('app.timezone'))->format('Y-m-d-His').'.pdf';
        $pdfCaption = 'Totales por tipo de pago — '.$report['cashier_name'].' ('.$report['opened_at_label'].' — '.$report['closed_at_label'].')';

        foreach ($phones as $phone) {
            try {
                $sentImage = false;

                if ($bannerImage !== null) {
                    $sentImage = $this->ultramsgWhatsAppClient->sendImageMessage($phone, $bannerImage, $caption);
                }

                if (! $sentImage) {
                    $this->ultramsgWhatsAppClient->sendTextMessage($phone, $caption);
                }

                $sentDocument = $this->ultramsgWhatsAppClient->sendDocumentMessage(
                    $phone,
                    $pdfDocument,
                    $pdfFilename,
                    $pdfCaption,
                );

                if (! $sentDocument) {
                    Log::warning('Cierre de caja física: no se pudo enviar PDF de totales por tipo de pago', [
                        'phone' => $phone,
                        'cashier_id' => $cashier->getKey(),
                        'physical_cash_box_id' => $physicalCashBox->getKey(),
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cierre de caja física: error al enviar WhatsApp a administrador', [
                    'phone' => $phone,
                    'cashier_id' => $cashier->getKey(),
                    'physical_cash_box_id' => $physicalCashBox->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array{
     *     cashier_name: string,
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     *     summary: array{
     *         sale_count: int,
     *         customers_served: int,
     *         quantity_sold: float,
     *         grand_total: float,
     *         payment_usd_sum: float,
     *         payment_ves_sum: float,
     *     },
     *     payment_breakdown: list<array{
     *         method: string,
     *         label: string,
     *         count: int,
     *         total_document: float,
     *         payment_usd: float,
     *         payment_ves: float,
     *     }>,
     *     payment_breakdown_totals: array{
     *         count: int,
     *         total_document: float,
     *         payment_usd: float,
     *         payment_ves: float,
     *     },
     * }  $report
     */
    private function buildCaption(array $report): string
    {
        $summary = $report['summary'];

        return implode("\n", [
            'CIERRE DE CAJA FISICA',
            (string) config('app.name'),
            '',
            'El cierre de caja se ejecuto con exito.',
            '',
            '[ TURNO ]',
            'Sucursal:'.$report['branch_name'],
            'Cajero:'.$report['cashier_name'],
            'Apertura:'.$report['opened_at_label'],
            'Cierre:'.$report['closed_at_label'],
            '',
            '[ RESUMEN ]',
            'Ventas:'.$this->formatInteger($summary['sale_count']),
            'Total ventas: USD '.$this->formatMoney($summary['grand_total']),
            '',
            'Reporte automatico al cerrar caja fisica.',
            'Adjunto: totales por tipo de pago (PDF).',
        ]);
    }

    /**
     * @return list<string>
     */
    private function resolveAdministratorPhones(): array
    {
        $adminPhones = User::query()
            ->get(['roles', 'whatsapp_phone', 'delivery_mobile_phone'])
            ->filter(fn (User $user): bool => $user->isAdministrator())
            ->map(fn (User $user): ?string => $this->normalizePhone(
                filled($user->whatsapp_phone) ? $user->whatsapp_phone : $user->delivery_mobile_phone
            ))
            ->filter()
            ->values()
            ->all();

        $fallbackPhones = collect(
            explode(',', (string) config('services.ultramsg.admin_fallback_phones', ''))
        )
            ->map(fn (string $phone): ?string => $this->normalizePhone($phone))
            ->filter()
            ->values()
            ->all();

        return array_values(array_unique([...$adminPhones, ...$fallbackPhones]));
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }

    private function formatInteger(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $raw = trim((string) $phone);
        $raw = preg_replace('/\s+/', '', $raw) ?? '';
        $raw = preg_replace('/[^0-9+]/', '', $raw) ?? '';

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '00')) {
            $raw = '+'.substr($raw, 2);
        }

        $digitsOnly = preg_replace('/\D/', '', $raw) ?? '';

        if (! str_starts_with($raw, '+')) {
            if (str_starts_with($digitsOnly, '0') && strlen($digitsOnly) === 11) {
                $raw = '+58'.substr($digitsOnly, 1);
            } elseif (str_starts_with($digitsOnly, '58') && strlen($digitsOnly) >= 10) {
                $raw = '+'.$digitsOnly;
            } elseif (str_starts_with($digitsOnly, '4') && strlen($digitsOnly) === 10) {
                $raw = '+58'.$digitsOnly;
            } else {
                $raw = '+'.$digitsOnly;
            }
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $raw;
    }
}
