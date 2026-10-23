<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Dashboard\CashierBranchGoalProgressService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Escritorio del cajero: meta del mes de su sucursal y su aporte, actualizado por polling.
 */
class CashierBranchGoalWidget extends Widget
{
    public const string POLLING_INTERVAL = '30s';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.cashier-branch-goal';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isCashier();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();

        return [
            'pollingInterval' => self::POLLING_INTERVAL,
            'progress' => app(CashierBranchGoalProgressService::class)->forCashier($user),
        ];
    }
}
