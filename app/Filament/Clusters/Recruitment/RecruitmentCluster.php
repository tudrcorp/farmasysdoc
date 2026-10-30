<?php

namespace App\Filament\Clusters\Recruitment;

use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RecruitmentCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserPlus;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reclutamiento';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return 'Reclutamiento';
    }

    public static function getClusterBreadcrumb(): string
    {
        return 'Reclutamiento';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->canAccessRecruitmentRequests() || $user->canAccessRecruitmentCandidates());
    }
}
