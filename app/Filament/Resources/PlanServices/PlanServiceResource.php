<?php

namespace App\Filament\Resources\PlanServices;

use App\Filament\Resources\Concerns\ViewableByMenuKeyManagedByAdministrators;
use App\Filament\Resources\PlanServices\Pages\ManagePlanServices;
use App\Filament\Resources\PlanServices\Schemas\PlanServiceForm;
use App\Filament\Resources\PlanServices\Tables\PlanServicesTable;
use App\Models\PlanService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PlanServiceResource extends Resource
{
    use ViewableByMenuKeyManagedByAdministrators;

    protected static ?string $model = PlanService::class;

    protected static ?string $slug = 'plan-services';

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'servicios de planes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Servicios de planes';

    protected static ?int $navigationSort = 6;

    protected static string|UnitEnum|null $navigationGroup = 'commercial_allies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Squares2x2;

    /**
     * Un servicio asignado a algún plan no se elimina: se desactiva.
     */
    public static function canDelete(Model $record): bool
    {
        return static::currentUserIsAdministrator()
            && $record instanceof PlanService
            && ! $record->plans()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return PlanServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanServicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlanServices::route('/'),
        ];
    }
}
