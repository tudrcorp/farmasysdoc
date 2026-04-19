<?php

namespace App\Filament\Resources\Rols;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\Rols\Pages\CreateRol;
use App\Filament\Resources\Rols\Pages\EditRol;
use App\Filament\Resources\Rols\Pages\ListRols;
use App\Filament\Resources\Rols\Pages\ViewRol;
use App\Filament\Resources\Rols\Schemas\RolForm;
use App\Filament\Resources\Rols\Schemas\RolInfolist;
use App\Filament\Resources\Rols\Tables\RolsTable;
use App\Models\Rol;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RolResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = Rol::class;

    protected static ?string $modelLabel = 'Rol';

    protected static ?string $pluralModelLabel = 'Roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return RolForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RolInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRols::route('/'),
            'create' => CreateRol::route('/create'),
            'view' => ViewRol::route('/{record}'),
            'edit' => EditRol::route('/{record}/edit'),
        ];
    }
}
