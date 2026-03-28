<?php

namespace App\Filament\Resources\PartnerCompanies;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\PartnerCompanies\Pages\CreatePartnerCompany;
use App\Filament\Resources\PartnerCompanies\Pages\EditPartnerCompany;
use App\Filament\Resources\PartnerCompanies\Pages\ListPartnerCompanies;
use App\Filament\Resources\PartnerCompanies\Pages\ViewPartnerCompany;
use App\Filament\Resources\PartnerCompanies\Schemas\PartnerCompanyForm;
use App\Filament\Resources\PartnerCompanies\Schemas\PartnerCompanyInfolist;
use App\Filament\Resources\PartnerCompanies\Tables\PartnerCompaniesTable;
use App\Models\PartnerCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PartnerCompanyResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = PartnerCompany::class;

    protected static ?string $navigationLabel = 'Compañías aliadas';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice2;

    public static function form(Schema $schema): Schema
    {
        return PartnerCompanyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PartnerCompanyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerCompaniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerCompanies::route('/'),
            'create' => CreatePartnerCompany::route('/create'),
            'view' => ViewPartnerCompany::route('/{record}'),
            'edit' => EditPartnerCompany::route('/{record}/edit'),
        ];
    }
}
