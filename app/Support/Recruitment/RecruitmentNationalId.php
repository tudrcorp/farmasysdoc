<?php

namespace App\Support\Recruitment;

use App\Models\Employee;
use App\Models\RecruitmentCandidate;

/**
 * Cédula venezolana: normaliza a `V-12345678` y compara solo por dígitos,
 * porque `employees.national_id` tiene formatos mixtos (`12345678`, `V-12345678`).
 */
final class RecruitmentNationalId
{
    public static function normalize(?string $value): ?string
    {
        $raw = mb_strtoupper(trim((string) $value), 'UTF-8');
        if (! preg_match('/^(?:C\.?\s*I\.?\s*:?\s*)?([VE])?[\s.\-]*(\d[\d\s.\-]*\d)$/u', $raw, $matches)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $matches[2]) ?? '';
        if (strlen($digits) < 6 || strlen($digits) > 9) {
            return null;
        }

        return ($matches[1] !== '' ? $matches[1] : 'V').'-'.$digits;
    }

    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function findEmployee(?string $value): ?Employee
    {
        $digits = self::digits($value);
        if ($digits === '') {
            return null;
        }

        return Employee::query()->whereRaw(self::digitsSql(), [$digits])->first();
    }

    public static function findCandidate(?string $value, ?int $exceptCandidateId = null): ?RecruitmentCandidate
    {
        $digits = self::digits($value);
        if ($digits === '') {
            return null;
        }

        return RecruitmentCandidate::query()
            ->when($exceptCandidateId !== null, fn ($query) => $query->whereKeyNot($exceptCandidateId))
            ->whereRaw(self::digitsSql(), [$digits])
            ->first();
    }

    private static function digitsSql(): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(national_id), ' ', ''), '.', ''), '-', ''), 'V', ''), 'E', '') = ?";
    }
}
