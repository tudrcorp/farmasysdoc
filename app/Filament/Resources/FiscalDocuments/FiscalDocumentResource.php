<?php

namespace App\Filament\Resources\FiscalDocuments;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\FiscalDocuments\Pages\ListFiscalDocuments;
use App\Filament\Resources\FiscalDocuments\Pages\ViewFiscalDocument;
use App\Filament\Resources\FiscalDocuments\Schemas\FiscalDocumentInfolist;
use App\Filament\Resources\FiscalDocuments\Tables\FiscalDocumentsTable;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FiscalDocumentResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = FiscalDocument::class;

    protected static ?string $navigationLabel = 'Documentos fiscales';

    protected static ?string $modelLabel = 'Documento fiscal';

    protected static ?string $pluralModelLabel = 'Documentos fiscales';

    protected static ?string $recordTitleAttribute = 'fiscal_number';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ReceiptPercent;

    public static function getNavigationGroup(): ?string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function infolist(Schema $schema): Schema
    {
        return FiscalDocumentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FiscalDocumentsTable::configure($table);
    }

    /**
     * @return Builder<FiscalDocument>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['fiscalPrinter.branch', 'sale'])
            ->whereIn('fiscal_printer_id', BranchAuthScope::apply(FiscalPrinter::query())->select('id'));
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiscalDocuments::route('/'),
            'view' => ViewFiscalDocument::route('/{record}'),
        ];
    }
}
