<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum ProductType: string
{
    use HasSpanishLabels;

    case Medication = 'medicamento';
    case Perfumery = 'perfumeria';
    case PersonalHygiene = 'higiene-personal';
    case Food = 'alimento';
    case MedicalEquipment = 'equipo-medico';

    public function label(): string
    {
        return match ($this) {
            self::Medication => 'Medicamento',
            self::Perfumery => 'Perfumería',
            self::PersonalHygiene => 'Higiene personal',
            self::Food => 'Alimento',
            self::MedicalEquipment => 'Equipo médico',
        };
    }
}
