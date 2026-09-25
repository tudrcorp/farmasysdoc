<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\User;
use App\Support\Reports\ProfitabilityKpiBoard;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use UnitEnum;

class ProfitabilityKpisPage extends Page
{
    protected static ?string $title = 'KPIs de rentabilidad';

    protected static ?string $navigationLabel = 'KPIs de rentabilidad';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static ?string $slug = 'kpis-rentabilidad';

    protected string $view = 'filament.pages.profitability-kpis';

    public ?string $branchId = null;

    public string $channel = 'all';

    public string $period = '';

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $month = $this->resolvedMonth();
        $branchId = filled($this->branchId) ? (int) $this->branchId : null;

        return [
            'board' => app(ProfitabilityKpiBoard::class)->snapshot($branchId, $this->channel, $month),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'periods' => $this->periodOptions(),
            'channel' => $this->channel,
            'branchId' => $this->branchId,
            'period' => $month->format('Y-m'),
        ];
    }

    private function resolvedMonth(): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $this->period) !== 1) {
            return now()->startOfMonth();
        }

        return Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function periodOptions(): array
    {
        $options = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $options[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->copy()->locale('es')->isoFormat('MMMM YYYY'),
            ];
            $cursor->subMonth();
        }

        return $options;
    }
}
