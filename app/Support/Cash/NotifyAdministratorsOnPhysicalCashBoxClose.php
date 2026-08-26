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

    /**
     * @param  array{
     *     expected_usd: float,
     *     expected_ves: float,
     *     declared_usd: float,
     *     declared_ves: float,
     * }|null  $reconciliationSnapshot
     */
    public function notify(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        CarbonInterface $openedAt,
        CarbonInterface $closedAt,
        ?array $reconciliationSnapshot = null,
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

        $report = $this->shiftReportBuilder->build(
            $cashier,
            $physicalCashBox,
            $openedAt,
            $closedAt,
            $reconciliationSnapshot,
        );
        $bannerImage = $this->ultramsgWhatsAppClient->resolvePhysicalCashBoxBannerImage();
        $caption = $this->buildCaption($report);
        $mediaCaption = $this->buildMediaCaption($report);
        $pdfBytes = $this->paymentTotalsPdfGenerator->generate($report);
        $pdfDocument = base64_encode($pdfBytes);
        $pdfFilename = 'totales-pago-cierre-caja-'.$closedAt->timezone((string) config('app.timezone'))->format('Y-m-d-His').'.pdf';

        foreach ($phones as $phone) {
            try {
                $sentArqueo = $this->ultramsgWhatsAppClient->sendTextMessage($phone, $caption);

                if (! $sentArqueo) {
                    Log::warning('Cierre de caja física: no se pudo enviar el arqueo por WhatsApp', [
                        'phone' => $phone,
                        'cashier_id' => $cashier->getKey(),
                        'physical_cash_box_id' => $physicalCashBox->getKey(),
                    ]);
                }

                if ($bannerImage !== null) {
                    $this->ultramsgWhatsAppClient->sendImageMessage($phone, $bannerImage, $mediaCaption);
                }

                $sentDocument = $this->ultramsgWhatsAppClient->sendDocumentMessage(
                    $phone,
                    $pdfDocument,
                    $pdfFilename,
                    $mediaCaption,
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
     *     close_detail: array{
     *         sale_count: int,
     *         total_usd: float,
     *         total_ves: float,
     *         punto_venta_ves: float,
     *         pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *         pago_movil_ves: float,
     *         transfer_ves: float,
     *         transfer_usd: float,
     *         efectivo_ves: float,
     *         efectivo_usd: float,
     *         usd_methods_total: float,
     *         ves_methods_total: float,
     *     },
     *     cash_box_reconciliation: array{
     *         movements_count: int,
     *         opening_usd: float,
     *         opening_ves: float,
     *         inbound_client_bill_usd: float,
     *         inbound_client_bill_usd_count: int,
     *         inbound_mixed_ves: float,
     *         inbound_mixed_ves_count: int,
     *         inbound_usd_total: float,
     *         inbound_ves_total: float,
     *         outbound_drawer_usd: float,
     *         outbound_drawer_usd_count: int,
     *         outbound_change_ves: float,
     *         outbound_change_ves_count: int,
     *         outbound_usd_total: float,
     *         outbound_ves_total: float,
     *         expected_usd: float,
     *         expected_ves: float,
     *         declared_usd: float,
     *         declared_ves: float,
     *         difference_usd: float,
     *         difference_ves: float,
     *         has_mismatch: bool,
     *     },
     * }  $report
     */
    private function buildCaption(array $report): string
    {
        $detail = $report['close_detail'];
        $reconciliation = $report['cash_box_reconciliation'];

        $lines = [
            'CONCILIACION DE CAJA FISICA',
            (string) config('app.name'),
            '',
            'El cierre de caja se ejecuto con exito.',
            '',
            '[ TURNO ]',
            'Sucursal: '.$report['branch_name'],
            'Cajero: '.$report['cashier_name'],
            'Apertura: '.$report['opened_at_label'],
            'Cierre: '.$report['closed_at_label'],
            'Movimientos de caja: '.$this->formatInteger($reconciliation['movements_count']),
            '',
            '[ APERTURA ]',
            'USD: '.$this->formatMoney($reconciliation['opening_usd']),
            'VES: '.$this->formatMoney($reconciliation['opening_ves']),
            '',
            '[ ENTRADAS ]',
            'Billetes del cliente (USD): '.$this->formatSignedMoney($reconciliation['inbound_client_bill_usd'])
                .'  '.$this->formatMovementCount($reconciliation['inbound_client_bill_usd_count']),
            'Efectivo VES (pago mixto): '.$this->formatSignedMoney($reconciliation['inbound_mixed_ves'])
                .'  '.$this->formatMovementCount($reconciliation['inbound_mixed_ves_count']),
            'Total entradas USD: '.$this->formatSignedMoney($reconciliation['inbound_usd_total']),
            'Total entradas VES: '.$this->formatSignedMoney($reconciliation['inbound_ves_total']),
            '',
            '[ SALIDAS ]',
            'USD retirados para vueltos: '.$this->formatSignedMoney(-1 * $reconciliation['outbound_drawer_usd'])
                .'  '.$this->formatMovementCount($reconciliation['outbound_drawer_usd_count']),
            'Vuelto VES entregado: '.$this->formatSignedMoney(-1 * $reconciliation['outbound_change_ves'])
                .'  '.$this->formatMovementCount($reconciliation['outbound_change_ves_count']),
            'Total salidas USD: '.$this->formatSignedMoney(-1 * $reconciliation['outbound_usd_total']),
            'Total salidas VES: '.$this->formatSignedMoney(-1 * $reconciliation['outbound_ves_total']),
            '',
            '[ ARQUEO ]',
            'Esperado sistema',
            'USD: '.$this->formatMoney($reconciliation['expected_usd']),
            'VES: '.$this->formatMoney($reconciliation['expected_ves']),
            '',
            'Declarado cajero',
            'USD: '.$this->formatMoney($reconciliation['declared_usd']),
            'VES: '.$this->formatMoney($reconciliation['declared_ves']),
            '',
            'Diferencia (declarado - esperado)',
            'USD: '.$this->formatSignedMoney($reconciliation['difference_usd']),
            'VES: '.$this->formatSignedMoney($reconciliation['difference_ves']),
            'Estado: '.($reconciliation['has_mismatch'] ? 'DESCUADRE' : 'CONCILIADO'),
        ];

        if ($reconciliation['has_mismatch']) {
            $lines[] = 'Revise billetes, vueltos y conteo fisico.';
        }

        $lines[] = '';
        $lines[] = '[ VENTAS DEL TURNO ]';
        $lines[] = 'Total de ventas: '.$this->formatInteger($detail['sale_count']);
        $lines[] = 'Total ventas USD: '.$this->formatMoney($detail['total_usd']);
        $lines[] = 'Total ventas VES: Bs. '.$this->formatMoney($detail['total_ves']);
        $lines[] = 'USD y VES no se convierten entre si.';
        $lines[] = 'Total Punto de Venta: Bs. '.$this->formatMoney($detail['punto_venta_ves']);

        foreach ($detail['pos_terminals'] as $terminal) {
            $lines[] = $terminal['label'].': Bs. '.$this->formatMoney((float) $terminal['amount_ves']);
        }

        $lines[] = 'Total Pago Movil: Bs. '.$this->formatMoney($detail['pago_movil_ves']);
        $lines[] = 'Total Transferencias VES: Bs. '.$this->formatMoney((float) ($detail['transfer_ves'] ?? 0));
        $lines[] = 'Total Transferencias USD: '.$this->formatMoney((float) ($detail['transfer_usd'] ?? 0));
        $lines[] = 'Efectivo VES: Bs. '.$this->formatMoney((float) ($detail['efectivo_ves'] ?? 0));
        $lines[] = 'Efectivo USD: '.$this->formatMoney((float) ($detail['efectivo_usd'] ?? 0));
        $lines[] = 'Total USD cobrado: '.$this->formatMoney($detail['usd_methods_total']);
        $lines[] = 'Total VES cobrado: Bs. '.$this->formatMoney($detail['ves_methods_total']);
        $lines[] = '';
        $lines[] = 'Reporte automatico al cerrar caja fisica.';
        $lines[] = 'Adjunto: totales por tipo de pago (PDF).';

        return implode("\n", $lines);
    }

    /**
     * Pie corto para imagen/PDF. UltraMsg limita captions de media a 1024 caracteres.
     *
     * @param  array{
     *     cashier_name: string,
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     * }  $report
     */
    private function buildMediaCaption(array $report): string
    {
        return implode("\n", [
            'CONCILIACION DE CAJA FISICA',
            'Sucursal: '.$report['branch_name'],
            'Cajero: '.$report['cashier_name'],
            'Cierre: '.$report['closed_at_label'],
            'El arqueo completo va en el mensaje de texto.',
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

    private function formatSignedMoney(float $amount): string
    {
        $formatted = $this->formatMoney(abs($amount));

        if (abs($amount) < 0.005) {
            return $formatted;
        }

        return ($amount > 0 ? '+' : '-').$formatted;
    }

    private function formatMovementCount(int $count): string
    {
        return '('.$this->formatInteger($count).' mov)';
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
