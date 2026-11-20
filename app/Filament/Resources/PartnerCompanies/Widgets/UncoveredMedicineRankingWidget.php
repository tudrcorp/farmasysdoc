<?php

namespace App\Filament\Resources\PartnerCompanies\Widgets;

use App\Filament\Resources\PartnerCompanies\Widgets\Concerns\ReadsUncoveredMedicinePartner;
use App\Models\PartnerUncoveredMedicine;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class UncoveredMedicineRankingWidget extends Widget
{
    use ReadsUncoveredMedicinePartner;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.resources.partner-companies.widgets.uncovered-medicine-ranking';

    public function getPollingInterval(): ?string
    {
        return $this->syncIsActive() ? '5s' : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'ranking' => $this->ranking(),
            'pollingInterval' => $this->getPollingInterval(),
        ];
    }

    /**
     * @return Collection<int, PartnerUncoveredMedicine>
     */
    private function ranking(): Collection
    {
        if ($this->partnerCompanyId < 1) {
            return collect();
        }

        return PartnerUncoveredMedicine::query()
            ->forPartner($this->partnerCompanyId)
            ->selectRaw('medicine_name, COUNT(*) as total')
            ->groupBy('medicine_name')
            ->orderByDesc('total')
            ->orderBy('medicine_name')
            ->limit(10)
            ->get();
    }
}
