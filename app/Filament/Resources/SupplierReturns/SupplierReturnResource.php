<?php

namespace App\Filament\Resources\SupplierReturns;

use App\Enums\SupplierReturnStatus;
use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\SupplierReturns\Pages\CreateSupplierReturn;
use App\Filament\Resources\SupplierReturns\Pages\EditSupplierReturn;
use App\Filament\Resources\SupplierReturns\Pages\ListSupplierReturns;
use App\Filament\Resources\SupplierReturns\Pages\ViewSupplierReturn;
use App\Filament\Resources\SupplierReturns\Schemas\SupplierReturnForm;
use App\Filament\Resources\SupplierReturns\Schemas\SupplierReturnInfolist;
use App\Filament\Resources\SupplierReturns\Tables\SupplierReturnsTable;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\Purchases\SupplierReturnWorkflow;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SupplierReturnResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = SupplierReturn::class;

    protected static ?string $slug = 'supplier-returns';

    protected static ?string $modelLabel = 'devolución';

    protected static ?string $pluralModelLabel = 'devoluciones';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $navigationLabel = 'Devoluciones';

    protected static ?int $navigationSort = 3;

    protected static string|UnitEnum|null $navigationGroup = 'purchases_payables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowUturnLeft;

    /**
     * @return Builder<SupplierReturn>
     */
    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery());
    }

    /**
     * Administrador: devoluciones por revisar. Sucursal: aprobadas pendientes de entregar.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();
        $status = $user instanceof User && $user->isAdministrator()
            ? SupplierReturnStatus::PendingReview
            : SupplierReturnStatus::Approved;

        $count = static::getEloquentQuery()->where('status', $status->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdministrator() ? 'warning' : 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdministrator() ? 'Devoluciones por revisar' : 'Devoluciones aprobadas por entregar al proveedor';
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        return static::canViewAny()
            && $record instanceof SupplierReturn
            && $user instanceof User
            && app(SupplierReturnWorkflow::class)->canEdit($user, $record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierReturnForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupplierReturnInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierReturns::route('/'),
            'create' => CreateSupplierReturn::route('/create'),
            'view' => ViewSupplierReturn::route('/{record}'),
            'edit' => EditSupplierReturn::route('/{record}/edit'),
        ];
    }
}
