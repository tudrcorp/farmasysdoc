<?php

namespace App\Filament\Resources\FiscalXReports;

use App\Enums\FiscalDocumentType;
use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\FiscalXReports\Pages\ListFiscalXReports;
use App\Filament\Resources\FiscalXReports\Tables\FiscalXReportsTable;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Historial de reportes X solicitados a las máquinas fiscales y acceso rápido para imprimir uno.
 * El X es informativo: no cierra la jornada y sus montos no se guardan.
 */
class FiscalXReportResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = FiscalDocument::class;

    protected static ?string $slug = 'fiscal-x-reports';

    protected static ?string $modelLabel = 'reporte X';

    protected static ?string $pluralModelLabel = 'reportes X';

    protected static ?string $navigationLabel = 'Reportes X';

    protected static ?int $navigationSort = 3;

    protected static string|UnitEnum|null $navigationGroup = 'sales_billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    /**
     * @return Builder<FiscalDocument>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', FiscalDocumentType::XReport->value)
            ->with('fiscalPrinter.branch')
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

    public static function table(Table $table): Table
    {
        return FiscalXReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiscalXReports::route('/'),
        ];
    }
}
