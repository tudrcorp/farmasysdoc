<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Support\Finance\DefaultVatRate;
use App\Support\Fiscal\FiscalPaymentCodes;

/**
 * Condiciones para pasar una máquina fiscal a modo «Activa». Se basa en lo que reporta el agente
 * (heartbeat) y en las ventas procesadas en modo «Simulación».
 */
final class FiscalPrinterActivationChecklist
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const FAIL = 'fail';

    /**
     * @return list<array{key: string, label: string, state: string, detail: string}>
     */
    public function evaluate(FiscalPrinter $printer): array
    {
        $status = is_array($printer->last_status) ? $printer->last_status : [];

        return [
            $this->cashBox($printer),
            $this->agentToken($printer),
            $this->agentOnline($printer),
            $this->registry($printer, $status),
            $this->taxRates($status),
            $this->paymentSlots($printer),
            $this->simulations($printer),
            $this->clock($status),
            $this->igtf($status),
        ];
    }

    public function canActivate(FiscalPrinter $printer): bool
    {
        return collect($this->evaluate($printer))->doesntContain('state', self::FAIL);
    }

    /**
     * @return list<string>
     */
    public function failures(FiscalPrinter $printer): array
    {
        return collect($this->evaluate($printer))
            ->where('state', self::FAIL)
            ->map(fn (array $item): string => $item['label'].': '.$item['detail'])
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function item(string $key, string $label, string $state, string $detail): array
    {
        return compact('key', 'label', 'state', 'detail');
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function cashBox(FiscalPrinter $printer): array
    {
        return $printer->physical_cash_box_id !== null
            ? $this->item('cash_box', 'Caja asignada', self::OK, (string) ($printer->physicalCashBox?->user?->name ?? 'Caja #'.$printer->physical_cash_box_id))
            : $this->item('cash_box', 'Caja asignada', self::FAIL, 'Asigne la caja del cajero que usa esta máquina.');
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function agentToken(FiscalPrinter $printer): array
    {
        return filled($printer->agent_token_hash)
            ? $this->item('token', 'Token del agente', self::OK, 'Configurado.')
            : $this->item('token', 'Token del agente', self::FAIL, 'Genere el token y configúrelo en el agente.');
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function agentOnline(FiscalPrinter $printer): array
    {
        if ($printer->isOnline()) {
            return $this->item('online', 'Agente en línea', self::OK, 'Último contacto '.$printer->last_heartbeat_at?->diffForHumans().'.');
        }

        return $this->item('online', 'Agente en línea', self::FAIL, $printer->last_heartbeat_at
            ? 'Sin contacto desde '.$printer->last_heartbeat_at->diffForHumans().'.'
            : 'El agente nunca se ha conectado.');
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function registry(FiscalPrinter $printer, array $status): array
    {
        $reported = (string) ($status['registered_machine_number'] ?? '');

        if ($reported === '') {
            return $this->item('registry', 'Máquina identificada', self::FAIL,
                'El agente aún no ha podido leer la máquina. Cierre un momento el sistema actual de la caja para que la lea.');
        }

        $expected = $this->normalize((string) $printer->fiscal_registry);
        $actual = $this->normalize($reported);

        return str_starts_with($expected, $actual) || str_starts_with($actual, $expected)
            ? $this->item('registry', 'Máquina identificada', self::OK, 'Registro '.$reported.'.')
            : $this->item('registry', 'Máquina identificada', self::FAIL, 'El agente ve la máquina '.$reported.', no '.$printer->fiscal_registry.'.');
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function taxRates(array $status): array
    {
        $rates = $status['tax_rates'] ?? null;

        if (! is_array($rates) || $rates === []) {
            return $this->item('tax_rates', 'Alícuotas de IVA', self::FAIL, 'Aún no se han leído de la máquina.');
        }

        $general = DefaultVatRate::percent();
        $programmed = array_map(fn (mixed $rate): float => round((float) $rate, 2), $rates);
        $text = implode(' / ', array_map(fn (float $rate): string => rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',').'%', $programmed));

        return in_array(round($general, 2), $programmed, true)
            ? $this->item('tax_rates', 'Alícuotas de IVA', self::OK, $text.'.')
            : $this->item('tax_rates', 'Alícuotas de IVA', self::FAIL, 'La máquina tiene '.$text.' y Farmadoc usa '.$general.'%.');
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function paymentSlots(FiscalPrinter $printer): array
    {
        $missing = FiscalPaymentCodes::missing(is_array($printer->payment_slots) ? $printer->payment_slots : null);

        if ($missing === []) {
            return $this->item('payment_slots', 'Medios de pago', self::OK, 'Todos asignados.');
        }

        $labels = FiscalPaymentCodes::labels();

        return $this->item('payment_slots', 'Medios de pago', self::FAIL,
            'Falta el número de: '.implode(', ', array_map(fn (string $code): string => $labels[$code], $missing)).'.');
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function simulations(FiscalPrinter $printer): array
    {
        $required = max(0, (int) config('fiscal.printers.min_simulations', 5));

        $simulated = FiscalDocument::query()
            ->where('fiscal_printer_id', $printer->id)
            ->where('simulation', true)
            ->where('is_test', false)
            ->where('status', FiscalDocumentStatus::Simulated);

        $ok = (clone $simulated)->whereNull('error_code')->count();
        $latest = (clone $simulated)->latest('id')->first();

        if ($latest instanceof FiscalDocument && filled($latest->error_code)) {
            return $this->item('simulations', 'Ventas simuladas', self::FAIL,
                'La última simulación falló: '.($latest->error_message ?? $latest->error_code).'.');
        }

        return $ok >= $required
            ? $this->item('simulations', 'Ventas simuladas', self::OK, $ok.' sin errores.')
            : $this->item('simulations', 'Ventas simuladas', self::FAIL, $ok.' de '.$required.' ventas simuladas sin errores.');
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function clock(array $status): array
    {
        if (! isset($status['clock_drift_seconds'])) {
            return $this->item('clock', 'Reloj de la máquina', self::WARNING, 'Aún no se ha leído.');
        }

        $drift = (int) $status['clock_drift_seconds'];
        $max = max(60, (int) config('fiscal.printers.max_clock_drift_seconds', 300));

        return abs($drift) <= $max
            ? $this->item('clock', 'Reloj de la máquina', self::OK, 'Diferencia de '.abs($drift).' s con la PC.')
            : $this->item('clock', 'Reloj de la máquina', self::WARNING,
                'Difiere '.round(abs($drift) / 60).' min de la PC; la factura saldrá con la hora de la máquina. Ajústelo con el técnico tras un Z.');
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function igtf(array $status): array
    {
        $rate = (float) ($status['igtf_rate'] ?? 0);

        return $rate > 0
            ? $this->item('igtf', 'IGTF en la máquina', self::OK, rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',').'%.')
            : $this->item('igtf', 'IGTF en la máquina', self::WARNING,
                'La máquina reporta IGTF 0%: el total impreso se comparará sin IGTF. Confírmelo con el técnico de HKA.');
    }

    private function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }
}
