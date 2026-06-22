<?php

namespace App\Filament\Resources\BranchSalesGoals;

use App\Filament\Resources\BranchSalesGoals\Pages\CreateBranchSalesGoal;
use App\Filament\Resources\BranchSalesGoals\Pages\EditBranchSalesGoal;
use App\Filament\Resources\BranchSalesGoals\Pages\ListBranchSalesGoals;
use App\Filament\Resources\BranchSalesGoals\Schemas\BranchSalesGoalForm;
use App\Filament\Resources\BranchSalesGoals\Tables\BranchSalesGoalsTable;
use App\Models\BranchSalesGoal;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BranchSalesGoalResource extends Resource
{
    protected static ?string $model = BranchSalesGoal::class;

    protected static ?string $navigationLabel = 'Metas de ventas';

    protected static ?string $modelLabel = 'Meta de ventas';

    protected static ?string $pluralModelLabel = 'Metas de ventas';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    public static function form(Schema $schema): Schema
    {
        return BranchSalesGoalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BranchSalesGoalsTable::configure($table);
    }

    /**
     * @return Builder<BranchSalesGoal>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('branch')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('is_global')
            ->orderBy('branch_id');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranchSalesGoals::route('/'),
            'create' => CreateBranchSalesGoal::route('/create'),
            'edit' => EditBranchSalesGoal::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        $user = request()->user() ?? Auth::user();

        return $user instanceof User && $user->isAdministrator();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }
}
