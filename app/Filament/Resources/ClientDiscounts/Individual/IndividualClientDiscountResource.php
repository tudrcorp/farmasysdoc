<?php

namespace App\Filament\Resources\ClientDiscounts\Individual;

use App\Filament\Clusters\ClientDiscounts\ClientDiscountsCluster;
use App\Filament\Resources\ClientDiscounts\Individual\Pages\CreateIndividualClientDiscount;
use App\Filament\Resources\ClientDiscounts\Individual\Pages\EditIndividualClientDiscount;
use App\Filament\Resources\ClientDiscounts\Individual\Pages\ListIndividualClientDiscounts;
use App\Filament\Resources\ClientDiscounts\Individual\Pages\ViewIndividualClientDiscount;
use App\Filament\Resources\ClientDiscounts\Individual\Schemas\IndividualClientDiscountForm;
use App\Filament\Resources\ClientDiscounts\Individual\Schemas\IndividualClientDiscountInfolist;
use App\Filament\Resources\ClientDiscounts\Individual\Tables\IndividualClientDiscountsTable;
use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Models\Client;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IndividualClientDiscountResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = Client::class;

    protected static ?string $cluster = ClientDiscountsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Individuales';

    protected static ?string $modelLabel = 'descuento individual';

    protected static ?string $pluralModelLabel = 'descuentos individuales';

    protected static ?string $slug = 'individuales';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::User;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('customer_discount', '>', 0)
            ->whereDoesntHave('discountGroups');
    }

    public static function form(Schema $schema): Schema
    {
        return IndividualClientDiscountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IndividualClientDiscountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndividualClientDiscountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndividualClientDiscounts::route('/'),
            'create' => CreateIndividualClientDiscount::route('/create'),
            'view' => ViewIndividualClientDiscount::route('/{record}'),
            'edit' => EditIndividualClientDiscount::route('/{record}/edit'),
        ];
    }
}
