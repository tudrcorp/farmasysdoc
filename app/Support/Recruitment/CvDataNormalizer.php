<?php

namespace App\Support\Recruitment;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Valida lo que devolvió la IA y marca los campos que el analista debe verificar.
 */
final class CvDataNormalizer
{
    /**
     * @var list<string>
     */
    private const array REQUIRED_FIELDS = ['first_name', 'last_name', 'national_id', 'phone', 'email', 'address'];

    /**
     * @var list<string>
     */
    private const array TEXT_FIELDS = ['first_name', 'last_name', 'address', 'desired_position', 'education', 'work_experience'];

    /**
     * @param  array<string, mixed>  $raw
     * @return array{attributes: array<string, mixed>, flags: list<string>}
     */
    public static function normalize(array $raw, ?int $candidateId = null): array
    {
        $attributes = [];
        foreach (self::TEXT_FIELDS as $field) {
            $attributes[$field] = self::text($raw[$field] ?? null);
        }

        $flags = array_values(array_filter(
            is_array($raw['uncertain_fields'] ?? null) ? $raw['uncertain_fields'] : [],
            'is_string',
        ));

        $attributes['national_id'] = RecruitmentNationalId::normalize(self::text($raw['national_id'] ?? null));
        $attributes['phone'] = self::phone(self::text($raw['phone'] ?? null), $flags);
        $attributes['email'] = self::email(self::text($raw['email'] ?? null));
        $attributes['birth_date'] = self::birthDate(self::text($raw['birth_date'] ?? null));
        $attributes['years_of_experience'] = self::yearsOfExperience($raw['years_of_experience'] ?? null);

        foreach (self::REQUIRED_FIELDS as $field) {
            if (blank($attributes[$field])) {
                $flags[] = $field;
            }
        }

        if (RecruitmentNationalId::findCandidate($attributes['national_id'], $candidateId) !== null) {
            $flags[] = 'national_id';
            $attributes['national_id'] = null;
        }

        return ['attributes' => $attributes, 'flags' => array_values(array_unique($flags))];
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    /**
     * @param  list<string>  $flags
     */
    private static function phone(?string $value, array &$flags): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (str_starts_with($digits, '58') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '4')) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return $digits;
        }

        $flags[] = 'phone';

        return $value;
    }

    private static function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = mb_strtolower($value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private static function birthDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        if (! $date || $date->age < 15 || $date->age > 90) {
            return null;
        }

        return $date->toDateString();
    }

    private static function yearsOfExperience(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $years = (int) $value;

        return $years >= 0 && $years <= 60 ? $years : null;
    }
}
