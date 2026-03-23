<?php

namespace App\Enums;

enum ProductType: string
{
    case Medication = 'medication';
    case Perfumery = 'perfumery';
    case PersonalHygiene = 'personal_hygiene';
    case Food = 'food';
    case MedicalEquipment = 'medical_equipment';
}
