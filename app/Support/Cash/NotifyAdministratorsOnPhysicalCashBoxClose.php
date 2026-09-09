<?php

namespace App\Support\Cash;

use App\Mail\PhysicalCashBoxCloseReportMail;
use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxCloseReport;
use App\Models\User;
use App\Services\Sales\PhysicalCashBoxShiftPaymentTotalsPdfGenerator;
use App\Services\Sales\PhysicalCashBoxShiftReportBuilder;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class NotifyAdministratorsOnPhysicalCashBoxClose
{
    public function __construct(
        private readonly PhysicalCashBoxShiftReportBuilder $shiftReportBuilder,
        private readonly PhysicalCashBoxShiftPaymentTotalsPdfGenerator $paymentTotalsPdfGenerator,
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
        private readonly PhysicalCashBoxShiftNotificationRecipients $recipients,
    ) {}

    /**
     * @param  array{
     *     expected_usd: float,
     *     expected_ves: float,
     *     declared_usd: float,
     *     declared_ves: float,
     *     declared_pos_lines?: list<array{bank_code: string, amount_ves: float}>,
     * }|null  $reconciliationSnapshot
     * @param  array<string, mixed>|null  $preparedReport
     */
    public function notify(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        CarbonInterface $openedAt,
        CarbonInterface $closedAt,
        ?array $reconciliationSnapshot = null,
        ?PhysicalCashBoxCloseReport $closeReport = null,
        ?array $preparedReport = null,
    ): void {
        $report = $preparedReport ?? $this->shiftReportBuilder->build(
            $cashier,
            $physicalCashBox,
            $openedAt,
            $closedAt,
            $reconciliationSnapshot,
        );
        $pdfBytes = $this->resolvePdfBytes($closeReport, $report);
        $pdfFilename = 'totales-pago-cierre-caja-'.$closedAt->timezone((string) config('app.timezone'))->format('Y-m-d-His').'.pdf';

        $whatsappError = $this->sendWhatsApp($cashier, $physicalCashBox, $report, $pdfBytes, $pdfFilename);
        $emailError = $this->sendEmail($cashier, $closeReport, $report, $pdfBytes, $pdfFilename);

        if ($closeReport instanceof PhysicalCashBoxCloseReport) {
            $closeReport->forceFill([
                'whatsapp_sent_at' => $whatsappError === null ? now() : $closeReport->whatsapp_sent_at,
                'email_sent_at' => $emailError === null ? now() : $closeReport->email_sent_at,
                'whatsapp_error' => $whatsappError,
                'email_error' => $emailError,
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function sendWhatsApp(
        User $cashier,
        PhysicalCashBox $physicalCashBox,
        array $report,
        string $pdfBytes,
        string $pdfFilename,
    ): ?string {
        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de cierre de caja física', [
                'cashier_id' => $cashier->getKey(),
                'physical_cash_box_id' => $physicalCashBox->getKey(),
            ]);

            return 'UltraMsg deshabilitado';
        }

        $phones = $this->recipients->phonesFor($cashier);

        if ($phones === []) {
            Log::notice('Cierre de caja física: sin teléfonos de gerentes o administradores para WhatsApp', [
                'cashier_id' => $cashier->getKey(),
                'physical_cash_box_id' => $physicalCashBox->getKey(),
            ]);

            return 'Sin teléfonos de gerentes o administradores';
        }

        $bannerImage = $this->ultramsgWhatsAppClient->resolvePhysicalCashBoxBannerImage();
        $caption = $this->buildCaption($report);
        $mediaCaption = $this->buildMediaCaption($report);
        $pdfDocument = $pdfBytes !== '' ? base64_encode($pdfBytes) : '';
        $anySent = false;
        $lastError = null;

        foreach ($phones as $phone) {
            try {
                $sentArqueo = $this->ultramsgWhatsAppClient->sendTextMessage($phone, $caption);

                if (! $sentArqueo) {
                    $lastError = 'No se pudo enviar el arqueo por WhatsApp';
                    Log::warning('Cierre de caja física: no se pudo enviar el arqueo por WhatsApp', [
                        'phone' => $phone,
                        'cashier_id' => $cashier->getKey(),
                        'physical_cash_box_id' => $physicalCashBox->getKey(),
                    ]);
                } else {
                    $anySent = true;
                }

                if ($bannerImage !== null) {
                    $this->ultramsgWhatsAppClient->sendImageMessage($phone, $bannerImage, $mediaCaption);
                }

                if ($pdfDocument !== '') {
                    $sentDocument = $this->ultramsgWhatsAppClient->sendDocumentMessage(
                        $phone,
                        $pdfDocument,
                        $pdfFilename,
                        $mediaCaption,
                    );

                    if (! $sentDocument) {
                        $lastError = 'No se pudo enviar el PDF por WhatsApp';
                        Log::warning('Cierre de caja física: no se pudo enviar PDF de totales por tipo de pago', [
                            'phone' => $phone,
                            'cashier_id' => $cashier->getKey(),
                            'physical_cash_box_id' => $physicalCashBox->getKey(),
                        ]);
                    }
                }
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
                Log::warning('Cierre de caja física: error al enviar WhatsApp a gerente o administrador', [
                    'phone' => $phone,
                    'cashier_id' => $cashier->getKey(),
                    'physical_cash_box_id' => $physicalCashBox->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $anySent ? null : ($lastError ?? 'No se pudo enviar WhatsApp');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function sendEmail(
        User $cashier,
        ?PhysicalCashBoxCloseReport $closeReport,
        array $report,
        string $pdfBytes,
        string $pdfFilename,
    ): ?string {
        $emails = $this->recipients->emailsFor($cashier);

        if ($emails === []) {
            Log::notice('Cierre de caja física: sin correos de gerentes o administradores', [
                'cashier_id' => $cashier->getKey(),
            ]);

            return 'Sin correos de gerentes o administradores';
        }

        $anySent = false;
        $lastError = null;

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new PhysicalCashBoxCloseReportMail(
                    report: $report,
                    closeReport: $closeReport,
                    pdfContents: $pdfBytes,
                    pdfFilename: $pdfFilename,
                ));
                $anySent = true;
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
                Log::warning('Cierre de caja física: error al enviar correo a gerente o administrador', [
                    'email' => $email,
                    'cashier_id' => $cashier->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $anySent ? null : ($lastError ?? 'No se pudo enviar el correo');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function resolvePdfBytes(?PhysicalCashBoxCloseReport $closeReport, array $report): string
    {
        if ($closeReport instanceof PhysicalCashBoxCloseReport && filled($closeReport->pdf_path)) {
            $stored = Storage::disk('local')->get((string) $closeReport->pdf_path);
            if (is_string($stored) && $stored !== '') {
                return $stored;
            }
        }

        try {
            return $this->paymentTotalsPdfGenerator->generate($report);
        } catch (Throwable $exception) {
            Log::warning('Cierre de caja física: no se pudo generar PDF para notificación', [
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @param  array{
     *     cashier_name: string,
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     *     close_detail: array{
     *         sale_count: int,
     *         total_usd: float,
     *         total_ves: float,
     *         punto_venta_ves: float,
     *         pos_terminals: list<array{id: int|null, label: string, amount_ves: float, bank_code?: string|null}>,
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
     *     pos_reconciliation?: array{
     *         lines: list<array{
     *             bank_code: string,
     *             bank_label: string,
     *             declared_ves: float,
     *             system_ves: float,
     *             difference_ves: float,
     *             status: string,
     *             status_label: string,
     *         }>,
     *         declared_total_ves: float,
     *         system_total_ves: float,
     *         difference_ves: float,
     *         has_mismatch: bool,
     *     },
     * }  $report
     */
    private function buildCaption(array $report): string
    {
        $detail = $report['close_detail'];
        $reconciliation = $report['cash_box_reconciliation'];
        $pos = is_array($report['pos_reconciliation'] ?? null) ? $report['pos_reconciliation'] : null;

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
            '[ ARQUEO EFECTIVO ]',
            'Esperado sistema',
            'USD: '.$this->formatMoney($reconciliation['expected_usd']),
            'VES: '.$this->formatMoney($reconciliation['expected_ves']),
            '',
            'Declarado cajero',
            'USD: '.$this->formatMoney($reconciliation['declared_usd']),
            'VES: '.$this->formatMoney($reconciliation['declared_ves']),
            '',
            'Diferencia (declarado - esperado)',
            'USD: '.$this->formatSignedMoney($reconciliation['difference_usd'])
                .'  '.$this->varianceLabel((float) $reconciliation['difference_usd']),
            'VES: '.$this->formatSignedMoney($reconciliation['difference_ves'])
                .'  '.$this->varianceLabel((float) $reconciliation['difference_ves']),
            'Estado efectivo: '.($reconciliation['has_mismatch'] ? 'DESCUADRE' : 'CONCILIADO'),
        ];

        if ($reconciliation['has_mismatch']) {
            $lines[] = 'Revise billetes, vueltos y conteo fisico.';
        }

        $lines[] = '';
        $lines[] = '[ PUNTO DE VENTA POR BANCO ]';

        if (is_array($pos) && ($pos['lines'] ?? []) !== []) {
            foreach ($pos['lines'] as $terminalLine) {
                $lines[] = $terminalLine['bank_label']
                    .': declarado Bs. '.$this->formatMoney((float) $terminalLine['declared_ves'])
                    .' | sistema Bs. '.$this->formatMoney((float) $terminalLine['system_ves'])
                    .' | '.$this->formatSignedMoney((float) $terminalLine['difference_ves'])
                    .' '.$terminalLine['status_label'];
            }

            $lines[] = 'Total POS declarado: Bs. '.$this->formatMoney((float) $pos['declared_total_ves']);
            $lines[] = 'Total POS sistema: Bs. '.$this->formatMoney((float) $pos['system_total_ves']);
            $lines[] = 'Estado POS: '.($pos['has_mismatch'] ? 'DESCUADRE' : 'CONCILIADO');
        } else {
            $lines[] = 'Sin declaraciones de punto de venta.';
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

    private function varianceLabel(float $difference): string
    {
        return PhysicalCashBoxCloseVariance::statusLabel($difference);
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
}
