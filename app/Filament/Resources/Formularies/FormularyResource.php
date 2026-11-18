<?php

namespace App\Filament\Resources\Formularies;

use App\Filament\Resources\Concerns\ViewableByMenuKeyManagedByAdministrators;
use App\Filament\Resources\Formularies\Pages\CreateFormulary;
use App\Filament\Resources\Formularies\Pages\EditFormulary;
use App\Filament\Resources\Formularies\Pages\ListFormularies;
use App\Filament\Resources\Formularies\Pages\ViewFormulary;
use App\Filament\Resources\Formularies\Schemas\FormularyForm;
use App\Filament\Resources\Formularies\Schemas\FormularyInfolist;
use App\Filament\Resources\Formularies\Tables\FormulariesTable;
use App\Models\Formulary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FormularyResource extends Resource
{
    use ViewableByMenuKeyManagedByAdministrators;

    protected static ?string $model = Formulary::class;

    protected static ?string $slug = 'formularies';

    protected static ?string $modelLabel = 'catálogo';

    protected static ?string $pluralModelLabel = 'catálogos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Catálogos';

    protected static ?int $navigationSort = 7;

    protected static string|UnitEnum|null $navigationGroup = 'commercial_allies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

    /**
     * Un catálogo asociado a algún plan no se elimina: se desactiva.
     */
    public static function canDelete(Model $record): bool
    {
        return static::currentUserIsAdministrator()
            && $record instanceof Formulary
            && ! $record->plans()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return FormularyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FormularyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FormulariesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormularies::route('/'),
            'create' => CreateFormulary::route('/create'),
            'view' => ViewFormulary::route('/{record}'),
            'edit' => EditFormulary::route('/{record}/edit'),
        ];
    }
}
