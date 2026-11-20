<?php

namespace App\Filament\Resources\PartnerCompanies\Widgets;

use App\Enums\UncoveredMedicineManagementStatus;
use App\Filament\Resources\PartnerCompanies\Widgets\Concerns\ReadsUncoveredMedicinePartner;
use App\Models\PartnerUncoveredMedicine;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UncoveredMedicineManagementStats extends StatsOverviewWidget
{
    use ReadsUncoveredMedicinePartner;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 2, '@lg' => 4];

    protected ?string $heading = 'Estatus de la gestión';

    protected ?string $description = 'Cómo está cada medicamento del lado de Tu Dr. Group, según la última copia local.';

    public function getPollingInterval(): ?string
    {
        return $this->syncIsActive() ? '5s' : null;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $counts = $this->counts();
        $known = 0;
        $stats = [];

        foreach (UncoveredMedicineManagementStatus::cases() as $status) {
            $total = (int) ($counts[$status->value] ?? 0);
            $known += $total;
            $stats[] = Stat::make($status->label(), (string) $total)
                ->color($status->color());
        }

        $others = array_sum($counts) - $known;
        if ($others > 0) {
            $stats[] = Stat::make('Otros', (string) $others)->color('gray');
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        if ($this->partnerCompanyId < 1) {
            return [];
        }

        return PartnerUncoveredMedicine::query()
            ->forPartner($this->partnerCompanyId)
            ->selectRaw('management_status, COUNT(*) as total')
            ->groupBy('management_status')
            ->get()
            ->mapWithKeys(fn (PartnerUncoveredMedicine $row): array => [(string) $row->getAttribute('management_status') => (int) $row->getAttribute('total')])
            ->all();
    }
}
