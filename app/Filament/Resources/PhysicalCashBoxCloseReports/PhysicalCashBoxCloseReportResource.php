<?php

namespace App\Filament\Resources\PhysicalCashBoxCloseReports;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\PhysicalCashBoxCloseReports\Pages\ListPhysicalCashBoxCloseReports;
use App\Filament\Resources\PhysicalCashBoxCloseReports\Pages\ViewPhysicalCashBoxCloseReport;
use App\Filament\Resources\PhysicalCashBoxCloseReports\Schemas\PhysicalCashBoxCloseReportInfolist;
use App\Filament\Resources\PhysicalCashBoxCloseReports\Tables\PhysicalCashBoxCloseReportsTable;
use App\Models\PhysicalCashBoxCloseReport;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PhysicalCashBoxCloseReportResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = PhysicalCashBoxCloseReport::class;

    protected static ?string $navigationLabel = 'Reportes de cierre de caja';

    protected static ?string $modelLabel = 'Reporte de cierre de caja';

    protected static ?string $pluralModelLabel = 'Reportes de cierre de caja';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    public static function getNavigationGroup(): ?string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function canViewAny(): bool
    {
        $user = request()->user() ?? Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->isAdministrator() && ! $user->hasGerenciaRole()) {
            return false;
        }

        if (FarmaadminDeliveryUserAccess::denies(static::class)) {
            return false;
        }

        if (! static::canAccessCurrentMenuItem()) {
            return false;
        }

        return static::getViewAnyAuthorizationResponse()->allowed();
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

    public static function canView(Model $record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    /**
     * @return Builder<PhysicalCashBoxCloseReport>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['user:id,name,email,branch_id', 'branch:id,name', 'physicalCashBox']);

        $user = Auth::user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdministrator()) {
            return $query;
        }

        if ($user->hasGerenciaRole()) {
            $branchIds = $user->restrictedBranchIdsForQueries();
            if ($branchIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn('branch_id', $branchIds);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PhysicalCashBoxCloseReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PhysicalCashBoxCloseReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPhysicalCashBoxCloseReports::route('/'),
            'view' => ViewPhysicalCashBoxCloseReport::route('/{record}'),
        ];
    }
}
