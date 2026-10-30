<?php

namespace App\Filament\Clusters\Severance;

use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SeveranceCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowRightStartOnRectangle;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'egresos';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return 'Egresos y liquidaciones';
    }

    public static function getClusterBreadcrumb(): string
    {
        return 'Egresos y liquidaciones';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->canAccessEmployeeExits() || $user->canAccessEmployeeSettlements());
    }
}
