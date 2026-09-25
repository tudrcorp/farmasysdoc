<?php

namespace App\Filament\Resources\MedicationQuotes;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\MedicationQuotes\Pages\CreateMedicationQuote;
use App\Filament\Resources\MedicationQuotes\Pages\EditMedicationQuote;
use App\Filament\Resources\MedicationQuotes\Pages\ListMedicationQuotes;
use App\Filament\Resources\MedicationQuotes\Pages\ViewMedicationQuote;
use App\Filament\Resources\MedicationQuotes\Schemas\MedicationQuoteForm;
use App\Filament\Resources\MedicationQuotes\Schemas\MedicationQuoteInfolist;
use App\Filament\Resources\MedicationQuotes\Tables\MedicationQuotesTable;
use App\Models\MedicationQuote;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MedicationQuoteResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = MedicationQuote::class;

    protected static ?string $navigationLabel = 'Cotizador';

    protected static ?string $modelLabel = 'cotización';

    protected static ?string $pluralModelLabel = 'cotizaciones';

    protected static ?int $navigationSort = 16;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function form(Schema $schema): Schema
    {
        return MedicationQuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MedicationQuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicationQuotesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicationQuotes::route('/'),
            'create' => CreateMedicationQuote::route('/create'),
            'view' => ViewMedicationQuote::route('/{record}'),
            'edit' => EditMedicationQuote::route('/{record}/edit'),
        ];
    }
}
