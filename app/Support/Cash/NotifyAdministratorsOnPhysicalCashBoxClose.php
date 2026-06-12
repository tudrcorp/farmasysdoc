<?php

namespace App\Support\Cash;

use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Services\Sales\PhysicalCashBoxShiftReportBuilder;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyAdministratorsOnPhysicalCashBoxClose
{
    public function __construct(
        private readonly PhysicalCashBoxShiftReportBuilder $shiftReportBuilder,
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
        $message = $this->buildMessage($report);

        foreach ($phones as $phone) {
            try {
                $this->ultramsgWhatsAppClient->sendTextMessage($phone, $message);
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
    private function buildMessage(array $report): string
    {
        $summary = $report['summary'];
        $totals = $report['payment_breakdown_totals'];

        $lines = [
            '========================================',
            'CIERRE DE CAJA FISICA',
            (string) config('app.name'),
            '========================================',
            '',
            '[ 1. TURNO ]',
            'Sucursal ....... '.$report['branch_name'],
            'Cajero ......... '.$report['cashier_name'],
            'Apertura ....... '.$report['opened_at_label'],
            'Cierre ......... '.$report['closed_at_label'],
            '',
            '[ 2. RESUMEN ]',
            'Ventas ......... '.$this->formatInteger($summary['sale_count']),
            'Clientes ....... '.$this->formatInteger($summary['customers_served']),
            'Productos ...... '.$this->formatQuantity($summary['quantity_sold']).' uds.',
            'Total ventas ... USD '.$this->formatMoney($summary['grand_total']),
            '',
            '[ 3. FORMAS DE PAGO ]',
        ];

        if ($report['payment_breakdown'] === []) {
            $lines[] = 'Sin ventas registradas en este turno.';
            $lines[] = '';
        } else {
            foreach ($report['payment_breakdown'] as $index => $row) {
                $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $lines[] = $number.'. '.$row['label'];
                $lines[] = '    Ventas ....... '.$this->formatInteger($row['count']);
                $lines[] = '    Total doc. ... USD '.$this->formatMoney($row['total_document']);

                if ($row['payment_usd'] > 0.00001) {
                    $lines[] = '    Cobrado USD .. USD '.$this->formatMoney($row['payment_usd']);
                }

                if ($row['payment_ves'] > 0.00001) {
                    $lines[] = '    Cobrado Bs ... Bs '.$this->formatMoney($row['payment_ves']);
                }

                $lines[] = '';
            }
        }

        $lines[] = '[ 4. TOTALES ]';
        $lines[] = 'Ventas ......... '.$this->formatInteger($totals['count']);
        $lines[] = 'Total doc. ..... USD '.$this->formatMoney($totals['total_document']);
        $lines[] = 'Cobrado USD .... USD '.$this->formatMoney($totals['payment_usd']);
        $lines[] = 'Cobrado Bs ..... Bs '.$this->formatMoney($totals['payment_ves']);
        $lines[] = '';
        $lines[] = '========================================';
        $lines[] = 'Reporte automatico al cierre de caja fisica.';

        return implode("\n", $lines);
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

    private function formatQuantity(float $quantity): string
    {
        if (abs($quantity - round($quantity)) < 0.0001) {
            return $this->formatInteger((int) round($quantity));
        }

        return number_format($quantity, 3, ',', '.');
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
