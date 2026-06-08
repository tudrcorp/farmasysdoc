<?php

namespace App\Filament\Resources\ConciliationCacheas;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\ConciliationCacheas\Pages\ListConciliationCacheas;
use App\Filament\Resources\ConciliationCacheas\Pages\ViewConciliationCachea;
use App\Filament\Resources\ConciliationCacheas\Schemas\ConciliationCacheaInfolist;
use App\Filament\Resources\ConciliationCacheas\Tables\ConciliationCacheasTable;
use App\Models\ConciliationCachea;
use App\Support\Sales\PosPaymentMethodOptions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ConciliationCacheaResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = ConciliationCachea::class;

    protected static ?string $navigationLabel = 'Conciliaciones Cachea';

    protected static ?string $modelLabel = 'conciliación Cachea';

    protected static ?string $pluralModelLabel = 'conciliaciones Cachea';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 46;

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return PosPaymentMethodOptions::cacheaNavigationIconUrl();
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConciliationCacheaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConciliationCacheasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConciliationCacheas::route('/'),
            'view' => ViewConciliationCachea::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
