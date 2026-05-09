<?php

namespace App\Filament\Resources\FarmaExpressCostStructures;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\FarmaExpressCostStructures\Pages\CreateFarmaExpressCostStructure;
use App\Filament\Resources\FarmaExpressCostStructures\Pages\EditFarmaExpressCostStructure;
use App\Filament\Resources\FarmaExpressCostStructures\Pages\ListFarmaExpressCostStructures;
use App\Filament\Resources\FarmaExpressCostStructures\Pages\ViewFarmaExpressCostStructure;
use App\Filament\Resources\FarmaExpressCostStructures\Schemas\FarmaExpressCostStructureForm;
use App\Filament\Resources\FarmaExpressCostStructures\Schemas\FarmaExpressCostStructureInfolist;
use App\Filament\Resources\FarmaExpressCostStructures\Tables\FarmaExpressCostStructuresTable;
use App\Models\FarmaExpressCostStructure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FarmaExpressCostStructureResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = FarmaExpressCostStructure::class;

    protected static ?string $navigationLabel = 'Estructura de Costos Express';

    protected static ?string $modelLabel = 'Estructura de costo express';

    protected static ?string $pluralModelLabel = 'Estructuras de costos express';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Calculator;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    public static function form(Schema $schema): Schema
    {
        return FarmaExpressCostStructureForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FarmaExpressCostStructureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FarmaExpressCostStructuresTable::configure($table);
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
            'index' => ListFarmaExpressCostStructures::route('/'),
            'create' => CreateFarmaExpressCostStructure::route('/create'),
            'view' => ViewFarmaExpressCostStructure::route('/{record}'),
            'edit' => EditFarmaExpressCostStructure::route('/{record}/edit'),
        ];
    }
}
