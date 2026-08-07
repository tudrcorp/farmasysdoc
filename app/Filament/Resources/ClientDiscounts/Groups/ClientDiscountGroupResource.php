<?php

namespace App\Filament\Resources\ClientDiscounts\Groups;

use App\Filament\Clusters\ClientDiscounts\ClientDiscountsCluster;
use App\Filament\Resources\ClientDiscounts\Groups\Pages\CreateClientDiscountGroup;
use App\Filament\Resources\ClientDiscounts\Groups\Pages\EditClientDiscountGroup;
use App\Filament\Resources\ClientDiscounts\Groups\Pages\ListClientDiscountGroups;
use App\Filament\Resources\ClientDiscounts\Groups\Pages\ViewClientDiscountGroup;
use App\Filament\Resources\ClientDiscounts\Groups\Schemas\ClientDiscountGroupForm;
use App\Filament\Resources\ClientDiscounts\Groups\Schemas\ClientDiscountGroupInfolist;
use App\Filament\Resources\ClientDiscounts\Groups\Tables\ClientDiscountGroupsTable;
use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Models\ClientDiscountGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClientDiscountGroupResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = ClientDiscountGroup::class;

    protected static ?string $cluster = ClientDiscountsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Grupos';

    protected static ?string $modelLabel = 'grupo de descuento';

    protected static ?string $pluralModelLabel = 'grupos de descuento';

    protected static ?string $slug = 'grupos';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    public static function form(Schema $schema): Schema
    {
        return ClientDiscountGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClientDiscountGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientDiscountGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientDiscountGroups::route('/'),
            'create' => CreateClientDiscountGroup::route('/create'),
            'view' => ViewClientDiscountGroup::route('/{record}'),
            'edit' => EditClientDiscountGroup::route('/{record}/edit'),
        ];
    }
}
