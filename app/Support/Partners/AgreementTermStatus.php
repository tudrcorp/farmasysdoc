<?php

namespace App\Support\Partners;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Vigencia de un convenio: días totales, transcurridos y restantes, con un estado
 * (vigente / por vencer / vence hoy / vencido / por iniciar) para mostrar al analista.
 */
final class AgreementTermStatus
{
    /**
     * @param  'ok'|'warning'|'danger'|'pending'|'unknown'  $tone
     */
    private function __construct(
        public readonly ?int $totalDays,
        public readonly ?int $remainingDays,
        public readonly ?int $elapsedDays,
        public readonly string $label,
        public readonly string $tone,
        public readonly ?CarbonImmutable $startsOn,
        public readonly ?CarbonImmutable $endsOn,
    ) {}

    public static function fromDates(mixed $startsOn, mixed $endsOn, ?CarbonInterface $today = null): self
    {
        $start = self::toDate($startsOn);
        $end = self::toDate($endsOn);
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();

        if ($end === null) {
            return new self(null, null, null, 'Sin fecha de vencimiento', 'unknown', $start, null);
        }

        if ($start !== null && $end->lt($start)) {
            return new self(null, null, null, 'La fecha de vencimiento es anterior a la de creación', 'danger', $start, $end);
        }

        $total = $start === null ? null : (int) $start->diffInDays($end) + 1;
        $remaining = (int) $today->diffInDays($end, false);
        $elapsed = $start === null ? null : max(0, min($total, (int) $start->diffInDays($today, false) + 1));

        if ($start !== null && $today->lt($start)) {
            $days = (int) $today->diffInDays($start);

            return new self($total, $remaining, 0, 'Inicia en '.self::days($days), 'pending', $start, $end);
        }

        [$label, $tone] = match (true) {
            $remaining < 0 => ['Vencido hace '.self::days(abs($remaining)), 'danger'],
            $remaining === 0 => ['Vence hoy', 'danger'],
            $remaining <= self::warningThreshold() => ['Quedan '.self::days($remaining), 'warning'],
            default => ['Quedan '.self::days($remaining), 'ok'],
        };

        return new self($total, $remaining, $elapsed, $label, $tone, $start, $end);
    }

    /**
     * Texto corto para formularios: «Vigencia: 365 días · Quedan 364 días · Vence el 11/11/2027».
     */
    public function summary(): string
    {
        if ($this->endsOn === null || $this->tone === 'danger' && $this->remainingDays === null) {
            return $this->label;
        }

        $parts = [];
        if ($this->totalDays !== null) {
            $parts[] = 'Vigencia: '.self::days($this->totalDays);
        }
        $parts[] = $this->label;
        $parts[] = ($this->remainingDays !== null && $this->remainingDays < 0 ? 'Venció el ' : 'Vence el ').$this->endsOn->format('d/m/Y');

        return implode(' · ', $parts);
    }

    /**
     * Porcentaje de la vigencia ya transcurrido (0–100), o null si no se puede calcular.
     */
    public function progressPercent(): ?int
    {
        if ($this->totalDays === null || $this->totalDays === 0 || $this->elapsedDays === null) {
            return null;
        }

        return (int) round(min(100, max(0, $this->elapsedDays / $this->totalDays * 100)));
    }

    /**
     * Color de Filament equivalente al estado.
     */
    public function color(): string
    {
        return match ($this->tone) {
            'ok' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            'pending' => 'info',
            default => 'gray',
        };
    }

    /**
     * Días antes del vencimiento a partir de los cuales se marca «por vencer» (primer aviso configurado).
     */
    public static function warningThreshold(): int
    {
        $days = (array) config('partner_agreements.expiry_notice_days', [60]);

        return $days === [] ? 60 : (int) max($days);
    }

    private static function days(int $days): string
    {
        return $days === 1 ? '1 día' : number_format($days, 0, ',', '.').' días';
    }

    private static function toDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
