<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\Branch;
use App\Models\User;
use App\Services\Marketing\MarketingAnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class MarketingBranchInsightsWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.marketing.branch-insights';

    public ?string $selectedBranchId = null;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey('marketing_hub');
    }

    public function updatedSelectedBranchId(?string $value): void
    {
        if ($value === '' || $value === null) {
            $this->selectedBranchId = null;
        }
    }

    /**
     * Misma sucursal: quita el filtro; otra: la selecciona (interacción tipo iOS).
     */
    public function toggleBranch(int $branchId): void
    {
        if ((string) $this->selectedBranchId === (string) $branchId) {
            $this->selectedBranchId = null;

            return;
        }

        $this->selectedBranchId = (string) $branchId;
    }

    public function clearBranchDetail(): void
    {
        $this->selectedBranchId = null;
    }

    /**
     * @return array{
     *     ranking: Collection<int, object>,
     *     branchOptions: Collection<int, string>,
     *     detail: ?array{name: string, top_product: string, top_clients: Collection<int, object>},
     *     selectedBranchId: ?string
     * }
     */
    protected function getViewData(): array
    {
        $analytics = app(MarketingAnalyticsService::class);
        $ranking = $analytics->branchRankingWithTopProducts(20);

        $branchOptions = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');

        $detail = null;
        $bid = $this->selectedBranchId !== null && $this->selectedBranchId !== ''
            ? (int) $this->selectedBranchId
            : null;

        if ($bid !== null && $bid > 0) {
            $detail = [
                'name' => (string) ($branchOptions[$bid] ?? 'Sucursal #'.$bid),
                'top_product' => $analytics->topProductNameForBranch($bid) ?? '—',
                'top_clients' => $analytics->topClientsByBranchPurchases($bid, 10),
            ];
        }

        return [
            'ranking' => $ranking,
            'branchOptions' => $branchOptions,
            'detail' => $detail,
            'selectedBranchId' => $this->selectedBranchId,
        ];
    }
}
