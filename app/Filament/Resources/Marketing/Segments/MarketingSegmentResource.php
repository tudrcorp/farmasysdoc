<?php

namespace App\Filament\Resources\Marketing\Segments;

use App\Filament\Resources\Marketing\Concerns\ChecksMarketingAccess;
use App\Filament\Resources\Marketing\Segments\Pages\CreateMarketingSegment;
use App\Filament\Resources\Marketing\Segments\Pages\EditMarketingSegment;
use App\Filament\Resources\Marketing\Segments\Pages\ListMarketingSegments;
use App\Filament\Resources\Marketing\Segments\Schemas\MarketingSegmentForm;
use App\Filament\Resources\Marketing\Segments\Tables\MarketingSegmentsTable;
use App\Models\MarketingSegment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketingSegmentResource extends Resource
{
    use ChecksMarketingAccess;

    protected static ?string $model = MarketingSegment::class;

    protected static ?string $navigationLabel = 'Segmentos';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 16;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    public static function form(Schema $schema): Schema
    {
        return MarketingSegmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingSegmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingSegments::route('/'),
            'create' => CreateMarketingSegment::route('/create'),
            'edit' => EditMarketingSegment::route('/{record}/edit'),
        ];
    }
}
