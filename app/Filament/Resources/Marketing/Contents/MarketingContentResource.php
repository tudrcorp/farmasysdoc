<?php

namespace App\Filament\Resources\Marketing\Contents;

use App\Filament\Resources\Marketing\Concerns\ChecksMarketingAccess;
use App\Filament\Resources\Marketing\Contents\Pages\CreateMarketingContent;
use App\Filament\Resources\Marketing\Contents\Pages\EditMarketingContent;
use App\Filament\Resources\Marketing\Contents\Pages\ListMarketingContents;
use App\Filament\Resources\Marketing\Contents\Schemas\MarketingContentForm;
use App\Filament\Resources\Marketing\Contents\Tables\MarketingContentsTable;
use App\Models\MarketingContent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketingContentResource extends Resource
{
    use ChecksMarketingAccess;

    protected static ?string $model = MarketingContent::class;

    protected static ?string $navigationLabel = 'Contenidos / promos';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 14;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Photo;

    public static function form(Schema $schema): Schema
    {
        return MarketingContentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingContentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingContents::route('/'),
            'create' => CreateMarketingContent::route('/create'),
            'edit' => EditMarketingContent::route('/{record}/edit'),
        ];
    }
}
