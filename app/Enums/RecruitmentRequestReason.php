<?php

namespace App\Enums;

enum RecruitmentRequestReason: string
{
    case Replacement = 'replacement';
    case NewPosition = 'new_position';
    case Temporary = 'temporary';

    public function label(): string
    {
        return match ($this) {
            self::Replacement => 'Reemplazo',
            self::NewPosition => 'Cargo nuevo',
            self::Temporary => 'Temporal',
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
}
