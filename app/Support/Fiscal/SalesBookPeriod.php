<?php

namespace App\Support\Fiscal;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Quincena fiscal del Libro de Ventas (sujeto pasivo especial): 1 = días 1–15, 2 = días 16–fin de mes.
 */
final readonly class SalesBookPeriod
{
    private const MONTHS = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
        7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];

    public function __construct(public int $year, public int $month, public int $half)
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || ! in_array($half, [1, 2], true)) {
            throw new InvalidArgumentException('Quincena inválida: '.$year.'-'.$month.' / '.$half.'.');
        }
    }

    public static function containing(DateTimeInterface|string $date): self
    {
        $day = CarbonImmutable::parse($date);

        return new self($day->year, $day->month, $day->day <= 15 ? 1 : 2);
    }

    /**
     * @param  string  $key  Formato «AAAA-MM-Q», p. ej. «2026-09-2».
     */
    public static function fromKey(string $key): self
    {
        if (preg_match('/^(\d{4})-(\d{2})-([12])$/', $key, $m) !== 1) {
            throw new InvalidArgumentException('Período inválido: '.$key.'.');
        }

        return new self((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    public function key(): string
    {
        return sprintf('%04d-%02d-%d', $this->year, $this->month, $this->half);
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, $this->half === 1 ? 1 : 16)->startOfDay();
    }

    public function end(): CarbonImmutable
    {
        $first = CarbonImmutable::create($this->year, $this->month, 1);

        return ($this->half === 1 ? $first->setDay(15) : $first->endOfMonth())->startOfDay();
    }

    public function contains(DateTimeInterface|string $date): bool
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return $day->betweenIncluded($this->start(), $this->end());
    }

    public function previous(): self
    {
        if ($this->half === 2) {
            return new self($this->year, $this->month, 1);
        }

        $prev = CarbonImmutable::create($this->year, $this->month, 1)->subMonth();

        return new self($prev->year, $prev->month, 2);
    }

    public function monthName(): string
    {
        return self::MONTHS[$this->month];
    }

    public function halfLabel(): string
    {
        return $this->half === 1 ? '1ra quincena' : '2da quincena';
    }

    public function label(): string
    {
        return $this->halfLabel().' de '.mb_strtolower($this->monthName()).' '.$this->year
            .' ('.$this->start()->format('d/m').' al '.$this->end()->format('d/m').')';
    }

    public function rangeLabel(): string
    {
        return $this->start()->format('d-m-Y').'  AL  '.$this->end()->format('d-m-Y');
    }

    public function hasEnded(?DateTimeInterface $now = null): bool
    {
        return CarbonImmutable::parse($now ?? now())->startOfDay()->greaterThan($this->end());
    }

    /**
     * Últimas quincenas (la actual primero), para selectores.
     *
     * @return array<string, string>
     */
    public static function recentOptions(int $count = 24): array
    {
        $period = self::containing(now());
        $options = [];

        for ($i = 0; $i < $count; $i++) {
            $options[$period->key()] = ucfirst($period->label());
            $period = $period->previous();
        }

        return $options;
    }
}
