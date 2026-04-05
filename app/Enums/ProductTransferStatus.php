<?php

namespace App\Enums;

/**
 * Estados permitidos para un traslado de producto entre sucursales.
 */
enum ProductTransferStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Color de badge en Filament (tabla / infolist).
     */
    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /**
     * Etiqueta en español para un valor guardado en BD (incluye valores legados).
     */
    public static function labelForStored(string|self|null $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim((string) $value));

        if (in_array($key, ['in_progress', 'en_proceso', 'en proceso'], true)) {
            return self::Pending->label();
        }

        $enum = self::tryFrom($key);
        if ($enum !== null) {
            return $enum->label();
        }

        return match ($key) {
            'pendiente' => self::Pending->label(),
            'completado', 'completada' => self::Completed->label(),
            'cancelado', 'cancelada' => self::Cancelled->label(),
            default => (string) $value,
        };
    }

    /**
     * Color de badge para un valor guardado en BD (incluye legado «en proceso» → pendiente).
     */
    public static function filamentColorForStored(string|self|null $value): string
    {
        if ($value instanceof self) {
            return $value->filamentColor();
        }

        if (blank($value)) {
            return 'gray';
        }

        $key = strtolower(trim((string) $value));

        if (in_array($key, ['in_progress', 'en_proceso', 'en proceso'], true)) {
            return self::Pending->filamentColor();
        }

        return self::tryFrom($key)?->filamentColor() ?? match ($key) {
            'pendiente' => self::Pending->filamentColor(),
            'completado', 'completada' => self::Completed->filamentColor(),
            'cancelado', 'cancelada' => self::Cancelled->filamentColor(),
            default => 'gray',
        };
    }

    public static function isCompletedValue(string|self|null $value): bool
    {
        if ($value instanceof self) {
            return $value === self::Completed;
        }

        return strtolower(trim((string) $value)) === self::Completed->value;
    }

    public static function isTerminalValue(string|self|null $value): bool
    {
        if ($value instanceof self) {
            return in_array($value, [self::Completed, self::Cancelled], true);
        }

        $key = strtolower(trim((string) $value));

        return in_array($key, [self::Completed->value, self::Cancelled->value], true);
    }
}
