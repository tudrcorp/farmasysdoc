<?php

namespace App\Filament\Resources\Plans;

use App\Filament\Resources\Concerns\ViewableByMenuKeyManagedByAdministrators;
use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Resources\Plans\Pages\ViewPlan;
use App\Filament\Resources\Plans\Schemas\PlanForm;
use App\Filament\Resources\Plans\Schemas\PlanInfolist;
use App\Filament\Resources\Plans\Tables\PlansTable;
use App\Models\Plan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PlanResource extends Resource
{
    use ViewableByMenuKeyManagedByAdministrators;

    protected static ?string $model = Plan::class;

    protected static ?string $slug = 'plans';

    protected static ?string $modelLabel = 'plan';

    protected static ?string $pluralModelLabel = 'planes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Planes';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = 'commercial_allies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    /**
     * Un plan asignado a algún aliado no se elimina: se inactiva.
     */
    public static function canDelete(Model $record): bool
    {
        return static::currentUserIsAdministrator()
            && $record instanceof Plan
            && ! $record->partnerCompanies()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'view' => ViewPlan::route('/{record}'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
