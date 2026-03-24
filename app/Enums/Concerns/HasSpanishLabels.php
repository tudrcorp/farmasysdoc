<?php

namespace App\Enums\Concerns;

trait HasSpanishLabels
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function tryLabel(mixed $state): string
    {
        if ($state instanceof self) {
            return $state->label();
        }
        if (is_string($state)) {
            return self::tryFrom($state)?->label() ?? $state;
        }

        return '—';
    }
}
