<?php

namespace App\Filament\Resources\FiscalPrinters;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\FiscalPrinters\Pages\CreateFiscalPrinter;
use App\Filament\Resources\FiscalPrinters\Pages\EditFiscalPrinter;
use App\Filament\Resources\FiscalPrinters\Pages\ListFiscalPrinters;
use App\Filament\Resources\FiscalPrinters\Pages\ViewFiscalPrinter;
use App\Filament\Resources\FiscalPrinters\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\FiscalPrinters\Schemas\FiscalPrinterForm;
use App\Filament\Resources\FiscalPrinters\Schemas\FiscalPrinterInfolist;
use App\Filament\Resources\FiscalPrinters\Tables\FiscalPrintersTable;
use App\Models\FiscalPrinter;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FiscalPrinterResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = FiscalPrinter::class;

    protected static ?string $navigationLabel = 'Máquinas fiscales';

    protected static ?string $modelLabel = 'Máquina fiscal';

    protected static ?string $pluralModelLabel = 'Máquinas fiscales';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 11;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Printer;

    public static function form(Schema $schema): Schema
    {
        return FiscalPrinterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FiscalPrinterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FiscalPrintersTable::configure($table);
    }

    /**
     * @return Builder<FiscalPrinter>
     */
    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery()->with(['branch', 'physicalCashBox.user']));
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
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiscalPrinters::route('/'),
            'create' => CreateFiscalPrinter::route('/create'),
            'view' => ViewFiscalPrinter::route('/{record}'),
            'edit' => EditFiscalPrinter::route('/{record}/edit'),
        ];
    }
}
