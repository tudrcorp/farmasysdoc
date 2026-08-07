<?php

namespace App\Filament\Clusters\ClientDiscounts;

use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ClientDiscountsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ReceiptPercent;

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'descuentos-clientes';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return 'Descuentos de clientes';
    }

    public static function getClusterBreadcrumb(): string
    {
        return 'Descuentos de clientes';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey('client_discounts');
    }
}
