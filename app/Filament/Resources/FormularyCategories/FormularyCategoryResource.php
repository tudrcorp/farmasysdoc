<?php

namespace App\Filament\Resources\FormularyCategories;

use App\Filament\Resources\Concerns\ViewableByMenuKeyManagedByAdministrators;
use App\Filament\Resources\FormularyCategories\Pages\CreateFormularyCategory;
use App\Filament\Resources\FormularyCategories\Pages\EditFormularyCategory;
use App\Filament\Resources\FormularyCategories\Pages\ListFormularyCategories;
use App\Filament\Resources\FormularyCategories\Pages\ViewFormularyCategory;
use App\Filament\Resources\FormularyCategories\Schemas\FormularyCategoryForm;
use App\Filament\Resources\FormularyCategories\Schemas\FormularyCategoryInfolist;
use App\Filament\Resources\FormularyCategories\Tables\FormularyCategoriesTable;
use App\Models\FormularyCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FormularyCategoryResource extends Resource
{
    use ViewableByMenuKeyManagedByAdministrators;

    protected static ?string $model = FormularyCategory::class;

    protected static ?string $slug = 'formulary-categories';

    protected static ?string $modelLabel = 'categoría de catálogo';

    protected static ?string $pluralModelLabel = 'categorías de catálogo';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Categorías de catálogo';

    protected static ?int $navigationSort = 8;

    protected static string|UnitEnum|null $navigationGroup = 'commercial_allies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleStack;

    /**
     * Una categoría usada por algún catálogo no se elimina: se desactiva.
     */
    public static function canDelete(Model $record): bool
    {
        return static::currentUserIsAdministrator()
            && $record instanceof FormularyCategory
            && ! $record->formularies()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return FormularyCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FormularyCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FormularyCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormularyCategories::route('/'),
            'create' => CreateFormularyCategory::route('/create'),
            'view' => ViewFormularyCategory::route('/{record}'),
            'edit' => EditFormularyCategory::route('/{record}/edit'),
        ];
    }
}
