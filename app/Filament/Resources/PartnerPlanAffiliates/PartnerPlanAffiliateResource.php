<?php

namespace App\Filament\Resources\PartnerPlanAffiliates;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\PartnerPlanAffiliates\Pages\ManagePartnerPlanAffiliates;
use App\Filament\Resources\PartnerPlanAffiliates\Tables\PartnerPlanAffiliatesTable;
use App\Models\PartnerPlanAffiliate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Afiliados de los planes de los aliados (clientes que pueden comprar por convenio). Se cargan
 * en bloque desde Excel/CSV o uno a uno; nunca se borran, se desactivan.
 */
class PartnerPlanAffiliateResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = PartnerPlanAffiliate::class;

    protected static ?string $slug = 'partner-plan-affiliates';

    protected static ?string $modelLabel = 'afiliado';

    protected static ?string $pluralModelLabel = 'afiliados de convenios';

    protected static ?string $navigationLabel = 'Afiliados de convenios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Identification;

    protected static string|UnitEnum|null $navigationGroup = 'commercial_allies';

    protected static ?int $navigationSort = 6;

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return PartnerPlanAffiliatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePartnerPlanAffiliates::route('/'),
        ];
    }
}
