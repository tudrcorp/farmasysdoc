<?php

namespace App\Filament\Resources\PartnerCompanies\Widgets;

use App\Enums\UncoveredMedicineInventoryStatus;
use App\Filament\Resources\PartnerCompanies\Widgets\Concerns\ReadsUncoveredMedicinePartner;
use App\Models\PartnerUncoveredMedicine;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UncoveredMedicineInventoryStats extends StatsOverviewWidget
{
    use ReadsUncoveredMedicinePartner;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 1, '@md' => 3];

    protected ?string $heading = 'Estado en el inventario';

    protected ?string $description = 'Color según la existencia en IntegraCorp al momento de la última actualización manual.';

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

        $stats = [
            $this->stat(UncoveredMedicineInventoryStatus::Available, $counts, Heroicon::CheckCircle),
            $this->stat(UncoveredMedicineInventoryStatus::Minimum, $counts, Heroicon::ExclamationTriangle),
            $this->stat(UncoveredMedicineInventoryStatus::Missing, $counts, Heroicon::XCircle),
        ];

        $known = 0;
        foreach (UncoveredMedicineInventoryStatus::cases() as $status) {
            $known += (int) ($counts[$status->value] ?? 0);
        }

        $others = array_sum($counts) - $known;
        if ($others > 0) {
            $stats[] = Stat::make('Otros', (string) $others)
                ->description('Estado de inventario no reconocido.')
                ->color('gray');
        }

        return $stats;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function stat(UncoveredMedicineInventoryStatus $status, array $counts, Heroicon $icon): Stat
    {
        return Stat::make($status->label(), (string) ($counts[$status->value] ?? 0))
            ->description($status->description())
            ->descriptionColor($status->color())
            ->color($status->color())
            ->icon($icon);
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
            ->selectRaw('inventory_status, COUNT(*) as total')
            ->groupBy('inventory_status')
            ->get()
            ->mapWithKeys(fn (PartnerUncoveredMedicine $row): array => [(string) $row->getAttribute('inventory_status') => (int) $row->getAttribute('total')])
            ->all();
    }
}
