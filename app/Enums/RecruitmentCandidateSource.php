<?php

namespace App\Enums;

enum RecruitmentCandidateSource: string
{
    case Referral = 'referral';
    case SocialMedia = 'social_media';
    case JobBoard = 'job_board';
    case WalkIn = 'walk_in';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Referral => 'Referido',
            self::SocialMedia => 'Redes sociales',
            self::JobBoard => 'Portal de empleo',
            self::WalkIn => 'Entregó el CV en sucursal',
            self::Other => 'Otro',
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
