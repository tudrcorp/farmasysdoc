<?php

namespace App\Filament\Resources\ConciliationBdvs;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\ConciliationBdvs\Pages\ListConciliationBdvs;
use App\Filament\Resources\ConciliationBdvs\Pages\ViewConciliationBdv;
use App\Filament\Resources\ConciliationBdvs\Schemas\ConciliationBdvInfolist;
use App\Filament\Resources\ConciliationBdvs\Tables\ConciliationBdvsTable;
use App\Models\ConciliationBdv;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ConciliationBdvResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = ConciliationBdv::class;

    protected static ?string $navigationLabel = 'Conciliaciones BDV';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    public static function table(Table $table): Table
    {
        return ConciliationBdvsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConciliationBdvInfolist::configure($schema);
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
            'index' => ListConciliationBdvs::route('/'),
            'view' => ViewConciliationBdv::route('/{record}'),
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
