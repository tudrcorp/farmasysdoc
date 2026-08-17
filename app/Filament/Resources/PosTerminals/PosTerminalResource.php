<?php

namespace App\Filament\Resources\PosTerminals;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\PosTerminals\Pages\CreatePosTerminal;
use App\Filament\Resources\PosTerminals\Pages\EditPosTerminal;
use App\Filament\Resources\PosTerminals\Pages\ListPosTerminals;
use App\Filament\Resources\PosTerminals\Pages\ViewPosTerminal;
use App\Filament\Resources\PosTerminals\Schemas\PosTerminalForm;
use App\Filament\Resources\PosTerminals\Schemas\PosTerminalInfolist;
use App\Filament\Resources\PosTerminals\Tables\PosTerminalsTable;
use App\Models\PosTerminal;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PosTerminalResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = PosTerminal::class;

    protected static ?string $navigationLabel = 'Puntos de venta';

    protected static ?string $modelLabel = 'Punto de venta';

    protected static ?string $pluralModelLabel = 'Puntos de venta';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CreditCard;

    public static function form(Schema $schema): Schema
    {
        return PosTerminalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PosTerminalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PosTerminalsTable::configure($table);
    }

    /**
     * @return Builder<PosTerminal>
     */
    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery()->with('branch'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosTerminals::route('/'),
            'create' => CreatePosTerminal::route('/create'),
            'view' => ViewPosTerminal::route('/{record}'),
            'edit' => EditPosTerminal::route('/{record}/edit'),
        ];
    }
}
