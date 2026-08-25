<?php

namespace App\Support\Branches;

use App\Models\Branch;
use App\Models\BranchDailyOperation;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyOnBranchDailyClose
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
        private readonly BranchDailyOperationRecipients $recipients,
    ) {}

    /**
     * @param  array{
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     *     closed_by_name: string,
     *     sale_count: int,
     *     total_usd: float,
     *     total_ves: float,
     *     pago_movil_ves: float,
     *     punto_venta_ves: float,
     *     pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }  $report
     */
    public function notify(User $actor, Branch $branch, BranchDailyOperation $operation, array $report): void
    {
        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de cierre de sucursal', [
                'branch_id' => $branch->getKey(),
                'operation_id' => $operation->getKey(),
            ]);

            return;
        }

        $phones = $this->recipients->phonesForBranch($branch);

        if ($phones === []) {
            Log::notice('Cierre de sucursal: sin teléfonos de administradores o gerentes para WhatsApp', [
                'branch_id' => $branch->getKey(),
                'operation_id' => $operation->getKey(),
                'closed_by_user_id' => $actor->getKey(),
            ]);

            return;
        }

        $message = $this->buildMessage($report);

        foreach ($phones as $phone) {
            try {
                $this->ultramsgWhatsAppClient->sendTextMessage($phone, $message);
            } catch (Throwable $exception) {
                Log::warning('Cierre de sucursal: error al enviar WhatsApp', [
                    'phone' => $phone,
                    'branch_id' => $branch->getKey(),
                    'operation_id' => $operation->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array{
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     *     closed_by_name: string,
     *     sale_count: int,
     *     total_usd: float,
     *     total_ves: float,
     *     pago_movil_ves: float,
     *     punto_venta_ves: float,
     *     pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }  $report
     */
    public function buildMessage(array $report): string
    {
        $lines = [
            'CONCILIACION GLOBAL DE SUCURSAL',
            (string) config('app.name'),
            '',
            'El cierre de la sucursal se ejecuto con exito.',
            '',
            '[ GESTION ]',
            'Sucursal:'.$report['branch_name'],
            'Apertura:'.$report['opened_at_label'],
            'Cierre:'.$report['closed_at_label'],
            'Responsable:'.$report['closed_by_name'],
            '',
            '[ RESUMEN ]',
            'Total de ventas:'.$this->formatInteger($report['sale_count']),
            'Total ventas USD: '.$this->formatMoney($report['total_usd']),
            'Total ventas VES: Bs. '.$this->formatMoney($report['total_ves']),
            '',
            '[ DETALLE ]',
            'Total Pago Movil: Bs. '.$this->formatMoney($report['pago_movil_ves']),
            'Total Punto de Venta: Bs. '.$this->formatMoney($report['punto_venta_ves']),
        ];

        foreach ($report['pos_terminals'] as $terminal) {
            $lines[] = $terminal['label'].': Bs. '.$this->formatMoney((float) $terminal['amount_ves']);
        }

        $lines[] = 'Total Transferencias VES: Bs. '.$this->formatMoney($report['transfer_ves']);
        $lines[] = 'Total Transferencias USD: '.$this->formatMoney($report['transfer_usd']);
        $lines[] = 'Efectivo VES: Bs. '.$this->formatMoney($report['efectivo_ves']);
        $lines[] = 'Efectivo USD: '.$this->formatMoney($report['efectivo_usd']);
        $lines[] = '';
        $lines[] = 'Reporte automatico al cerrar la sucursal.';

        return implode("\n", $lines);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }

    private function formatInteger(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
