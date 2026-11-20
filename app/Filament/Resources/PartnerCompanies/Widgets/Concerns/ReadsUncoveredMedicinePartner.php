<?php

namespace App\Filament\Resources\PartnerCompanies\Widgets\Concerns;

use App\Models\PartnerUncoveredMedicineConnection;

trait ReadsUncoveredMedicinePartner
{
    public int $partnerCompanyId = 0;

    protected function connection(): ?PartnerUncoveredMedicineConnection
    {
        if ($this->partnerCompanyId < 1) {
            return null;
        }

        return PartnerUncoveredMedicineConnection::query()
            ->where('partner_company_id', $this->partnerCompanyId)
            ->first();
    }

    protected function syncIsActive(): bool
    {
        return $this->connection()?->isSyncActive() ?? false;
    }
}
