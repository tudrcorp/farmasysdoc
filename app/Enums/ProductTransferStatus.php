<?php

namespace App\Enums;

/**
 * Estados del flujo de traslado: solicitante crea Pendiente → delivery En proceso → receptor Completado.
 */
enum ProductTransferStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InProgress => 'En proceso',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Opciones del flujo normal (creación y operación).
     *
     * @return array<string, string>
     */
    public static function workflowOptions(): array
    {
        return [
            self::Pending->value => self::Pending->label(),
            self::InProgress->value => self::InProgress->label(),
            self::Completed->value => self::Completed->label(),
        ];
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

    public static function labelForStored(string|self|null $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim((string) $value));

        $enum = self::tryFrom($key);
        if ($enum !== null) {
            return $enum->label();
        }

        return match ($key) {
            'pendiente' => self::Pending->label(),
            'en_proceso', 'en proceso', 'en-proceso' => self::InProgress->label(),
            'completado', 'completada' => self::Completed->label(),
            'cancelado', 'cancelada' => self::Cancelled->label(),
            default => (string) $value,
        };
    }

    public static function filamentColorForStored(string|self|null $value): string
    {
        if ($value instanceof self) {
            return $value->filamentColor();
        }

        if (blank($value)) {
            return 'gray';
        }

        $key = strtolower(trim((string) $value));

        return self::tryFrom($key)?->filamentColor() ?? match ($key) {
            'pendiente' => self::Pending->filamentColor(),
            'en_proceso', 'en proceso', 'en-proceso' => self::InProgress->filamentColor(),
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

    public static function isInProgressValue(string|self|null $value): bool
    {
        if ($value instanceof self) {
            return $value === self::InProgress;
        }

        $key = strtolower(trim((string) $value));

        return $key === self::InProgress->value
            || in_array($key, ['en_proceso', 'en proceso', 'en-proceso'], true);
    }

    public static function isTerminalValue(string|self|null $value): bool
    {
        if ($value instanceof self) {
            return in_array($value, [self::Completed, self::Cancelled], true);
        }

        $key = strtolower(trim((string) $value));

        return in_array($key, [self::Completed->value, self::Cancelled->value], true)
            || in_array($key, ['completado', 'completada', 'cancelado', 'cancelada'], true);
    }
}
