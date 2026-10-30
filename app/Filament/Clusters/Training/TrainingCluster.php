<?php

namespace App\Filament\Clusters\Training;

use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class TrainingCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'capacitaciones';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return 'Talleres y capacitaciones';
    }

    public static function getClusterBreadcrumb(): string
    {
        return 'Talleres y capacitaciones';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->canManageTraining() || $user->canAccessTrainingSessions() || $user->canAccessTrainingRecords());
    }
}
