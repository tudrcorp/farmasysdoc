<?php

namespace App\Filament\Resources\FiscalZReports;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\FiscalZReports\Pages\ListFiscalZReports;
use App\Filament\Resources\FiscalZReports\Pages\ViewFiscalZReport;
use App\Filament\Resources\FiscalZReports\Schemas\FiscalZReportInfolist;
use App\Filament\Resources\FiscalZReports\Tables\FiscalZReportsTable;
use App\Models\FiscalZReport;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Consulta de reportes Z de todas las máquinas fiscales (solo lectura: los Z se capturan desde la máquina).
 */
class FiscalZReportResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = FiscalZReport::class;

    protected static ?string $slug = 'fiscal-z-reports';

    protected static ?string $modelLabel = 'reporte Z';

    protected static ?string $pluralModelLabel = 'reportes Z';

    protected static ?string $navigationLabel = 'Reportes Z';

    protected static ?int $navigationSort = 4;

    protected static string|UnitEnum|null $navigationGroup = 'sales_billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::LockClosed;

    /**
     * @return Builder<FiscalZReport>
     */
    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery()->with(['fiscalPrinter:id,name,branch_id', 'branch:id,name']));
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

    public static function infolist(Schema $schema): Schema
    {
        return FiscalZReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FiscalZReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiscalZReports::route('/'),
            'view' => ViewFiscalZReport::route('/{record}'),
        ];
    }
}
