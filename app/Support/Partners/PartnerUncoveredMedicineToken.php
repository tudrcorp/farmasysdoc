<?php

namespace App\Support\Partners;

use App\Enums\UncoveredMedicineSyncStatus;
use App\Models\PartnerCompany;
use App\Models\PartnerUncoveredMedicineConnection;

/**
 * Guarda el token Bearer del aliado sin volver a mostrarlo y sin borrarlo si el campo llega vacío.
 */
class PartnerUncoveredMedicineToken
{
    public static function store(PartnerCompany $company, mixed $token): void
    {
        if (! is_string($token)) {
            return;
        }

        $token = trim($token);
        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        if ($token === '') {
            return;
        }

        $connection = $company->uncoveredMedicineConnection;
        if ($connection instanceof PartnerUncoveredMedicineConnection && $connection->token === $token) {
            return;
        }

        if ($connection instanceof PartnerUncoveredMedicineConnection) {
            $connection->forceFill([
                'token' => $token,
                'last_error' => null,
            ])->save();

            return;
        }

        $company->uncoveredMedicineConnection()->create([
            'token' => $token,
            'sync_status' => UncoveredMedicineSyncStatus::Idle,
        ]);
    }
}
