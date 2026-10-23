<?php

namespace App\Support\Filament;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Rango de fechas compartido en el dashboard Farmaadmin (persistido en sesión por usuario).
 * Por defecto: del día 1 del mes en curso hasta hoy.
 */
final class DashboardDateRangeFilter
{
    private const SESSION_PREFIX = 'farmaadmin.dashboard.date_range.';

    public const int MAX_RANGE_DAYS = 366;

    public static function canPickRange(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->isAdministrator() || $user->isManager());
    }

    public static function setRange(?string $from, ?string $to): void
    {
        $start = self::parseDate($from);
        $end = self::parseDate($to);

        if (! self::canPickRange() || $start === null || $end === null) {
            self::reset();

            return;
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $today = now()->startOfDay();

        if ($end->greaterThan($today)) {
            $end = $today;
        }

        if ($start->greaterThan($end)) {
            $start = $end;
        }

        if ($start->diffInDays($end) + 1 > self::MAX_RANGE_DAYS) {
            $start = $end->subDays(self::MAX_RANGE_DAYS - 1);
        }

        if ($start->isSameDay(self::defaultFrom()) && $end->isSameDay($today)) {
            self::reset();

            return;
        }

        session([self::sessionKeyForCurrentUser() => [
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
        ]]);
    }

    public static function reset(): void
    {
        session()->forget(self::sessionKeyForCurrentUser());
    }

    public static function from(): CarbonImmutable
    {
        return self::storedRange()['from'] ?? self::defaultFrom();
    }

    public static function to(): CarbonImmutable
    {
        return (self::storedRange()['to'] ?? now()->startOfDay())->endOfDay();
    }

    public static function fromKey(): string
    {
        return self::from()->format('Y-m-d');
    }

    public static function toKey(): string
    {
        return self::to()->format('Y-m-d');
    }

    public static function rangeKey(): string
    {
        return self::fromKey().'_'.self::toKey();
    }

    public static function isMonthToDate(): bool
    {
        return self::storedRange() === null;
    }

    public static function includesToday(): bool
    {
        return self::to()->isSameDay(now());
    }

    public static function isSingleMonth(): bool
    {
        return self::from()->format('Y-m') === self::to()->format('Y-m');
    }

    public static function label(): string
    {
        return self::labelFor(self::from(), self::to());
    }

    /**
     * Etiqueta para encabezados: "Hoy · 23 oct 2026", "Octubre 2026" o el rango.
     */
    public static function displayLabel(): string
    {
        if (self::from()->isSameDay(now()) && self::includesToday()) {
            return 'Hoy · '.self::label();
        }

        if (self::isMonthToDate()) {
            return ucfirst(self::from()->locale('es')->translatedFormat('F Y'));
        }

        return self::label();
    }

    public static function labelFor(CarbonInterface $from, CarbonInterface $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->day.' '.self::shortMonth($from).' '.$from->year;
        }

        if ($from->format('Y-m') === $to->format('Y-m')) {
            return $from->day.' – '.$to->day.' '.self::shortMonth($to).' '.$to->year;
        }

        if ($from->year === $to->year) {
            return $from->day.' '.self::shortMonth($from).' – '.$to->day.' '.self::shortMonth($to).' '.$to->year;
        }

        return $from->day.' '.self::shortMonth($from).' '.$from->year.' – '.$to->day.' '.self::shortMonth($to).' '.$to->year;
    }

    /**
     * @return list<array{key: string, label: string, from: string, to: string}>
     */
    public static function presetsForPicker(): array
    {
        $today = now()->startOfDay();
        $lastMonth = $today->startOfMonth()->subMonth();

        $presets = [
            'today' => ['Hoy', $today, $today],
            'yesterday' => ['Ayer', $today->subDay(), $today->subDay()],
            'last_7_days' => ['Últimos 7 días', $today->subDays(6), $today],
            'last_30_days' => ['Últimos 30 días', $today->subDays(29), $today],
            'this_month' => ['Este mes', $today->startOfMonth(), $today],
            'last_month' => ['Mes pasado', $lastMonth, $lastMonth->endOfMonth()->startOfDay()],
        ];

        $options = [];

        foreach ($presets as $key => [$label, $from, $to]) {
            $options[] = [
                'key' => $key,
                'label' => $label,
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ];
        }

        return $options;
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}|null
     */
    private static function storedRange(): ?array
    {
        if (! self::canPickRange()) {
            return null;
        }

        $value = session(self::sessionKeyForCurrentUser());

        if (! is_array($value)) {
            return null;
        }

        $from = self::parseDate($value['from'] ?? null);
        $to = self::parseDate($value['to'] ?? null);

        if ($from === null || $to === null || $from->greaterThan($to) || $to->greaterThan(now()->startOfDay())) {
            return null;
        }

        return ['from' => $from, 'to' => $to];
    }

    private static function defaultFrom(): CarbonImmutable
    {
        return now()->startOfMonth()->startOfDay();
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date->startOfDay();
    }

    private static function shortMonth(CarbonInterface $date): string
    {
        return rtrim($date->locale('es')->translatedFormat('M'), '.');
    }

    private static function sessionKeyForCurrentUser(): string
    {
        $userId = Auth::id();

        return self::SESSION_PREFIX.($userId ?? 'guest');
    }
}
