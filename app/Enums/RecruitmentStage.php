<?php

namespace App\Enums;

enum RecruitmentStage: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Interview = 'interview';
    case Evaluation = 'evaluation';
    case Offer = 'offer';
    case Hired = 'hired';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Postulado',
            self::Screening => 'Preselección',
            self::Interview => 'Entrevista',
            self::Evaluation => 'Evaluación',
            self::Offer => 'Oferta',
            self::Hired => 'Contratado',
            self::Discarded => 'Descartado',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Applied => 'gray',
            self::Screening => 'info',
            self::Interview => 'primary',
            self::Evaluation => 'warning',
            self::Offer => 'warning',
            self::Hired => 'success',
            self::Discarded => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Hired, self::Discarded], true);
    }

    /**
     * Etapas a las que se puede mover un candidato libremente.
     * Contratado y Descartado tienen acciones propias porque exigen datos adicionales.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Applied, self::Screening, self::Interview, self::Evaluation, self::Offer];
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
