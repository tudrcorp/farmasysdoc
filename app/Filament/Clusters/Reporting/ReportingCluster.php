<?php

namespace App\Filament\Clusters\Reporting;

use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ReportingCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentChartBar;

    protected static ?int $navigationSort = 14;

    protected static ?string $slug = 'reporteria';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return 'Reportería';
    }

    public static function getClusterBreadcrumb(): string
    {
        return 'Reportería';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        return $user->hasGerenciaRole() && $user->canAccessFarmaadminMenuKey('financial_report');
    }
}
