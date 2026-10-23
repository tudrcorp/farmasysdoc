<?php

namespace App\Enums;

enum EmployeeRequestType: string
{
    case EmploymentCertificate = 'employment_certificate';
    case Vacation = 'vacation';
    case Leave = 'leave';
    case MedicalRest = 'medical_rest';

    public function label(): string
    {
        return match ($this) {
            self::EmploymentCertificate => 'Constancia de trabajo',
            self::Vacation => 'Vacaciones',
            self::Leave => 'Permiso',
            self::MedicalRest => 'Reposo',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::EmploymentCertificate => 'info',
            self::Vacation => 'success',
            self::Leave => 'warning',
            self::MedicalRest => 'danger',
        };
    }

    /**
     * Slug usado en las URLs del portal del empleado.
     */
    public function portalSlug(): string
    {
        return match ($this) {
            self::EmploymentCertificate => 'constancia',
            self::Vacation => 'vacaciones',
            self::Leave => 'permiso',
            self::MedicalRest => 'reposo',
        };
    }

    public static function fromPortalSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->portalSlug() === $slug) {
                return $case;
            }
        }

        return null;
    }

    public function hasDateRange(): bool
    {
        return $this !== self::EmploymentCertificate;
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
