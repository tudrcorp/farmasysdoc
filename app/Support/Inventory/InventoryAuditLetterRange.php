<?php

namespace App\Support\Inventory;

use Illuminate\Validation\ValidationException;

final class InventoryAuditLetterRange
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $letters = range('A', 'Z');

        return array_combine($letters, $letters);
    }

    public static function normalize(?string $letter): ?string
    {
        if (! filled($letter)) {
            return null;
        }

        $normalized = strtoupper(trim((string) $letter));

        if (strlen($normalized) !== 1 || ! ctype_alpha($normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function resolve(?string $from, ?string $to): ?array
    {
        $letterFrom = self::normalize($from);
        $letterTo = self::normalize($to);

        if ($letterFrom === null && $letterTo === null) {
            return null;
        }

        if ($letterFrom === null || $letterTo === null) {
            throw ValidationException::withMessages([
                'letter_from' => 'Debe indicar letra inicial y final del rango.',
                'letter_to' => 'Debe indicar letra inicial y final del rango.',
            ]);
        }

        if ($letterFrom > $letterTo) {
            throw ValidationException::withMessages([
                'letter_to' => 'La letra final debe ser igual o posterior a la inicial.',
            ]);
        }

        return [$letterFrom, $letterTo];
    }

    public static function label(?string $from, ?string $to): ?string
    {
        $range = null;

        try {
            $range = self::resolve($from, $to);
        } catch (ValidationException) {
            return null;
        }

        if ($range === null) {
            return null;
        }

        return $range[0].' – '.$range[1];
    }
}
