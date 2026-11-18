<?php

namespace App\Filament\BusinessPartners\Resources\HistoricalOfMovements;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Pages\ListHistoricalOfMovements;
use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Pages\ViewHistoricalOfMovement;
use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Schemas\HistoricalOfMovementForm;
use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Schemas\HistoricalOfMovementInfolist;
use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Tables\HistoricalOfMovementsTable;
use App\Models\HistoricalOfMovement;
use App\Models\PartnerCompany;
use App\Models\User;
use App\Support\Partners\PartnerPanel;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HistoricalOfMovementResource extends Resource
{
    protected static ?string $model = HistoricalOfMovement::class;

    protected static ?string $navigationLabel = 'Estado de cuenta';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'Estado de cuenta';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Banknotes;

    public static function getNavigationGroup(): ?string
    {
        return 'Mi gestión';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::currentPartnerHasAssignedCredit();
    }

    public static function canViewAny(): bool
    {
        return self::currentPartnerHasAssignedCredit();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return self::currentPartnerHasAssignedCredit()
            && self::recordBelongsToCurrentPartnerCompany($record);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Filament::auth()->user();

        if ($user instanceof User && $user->isPartnerCompanyUser()) {
            return $query->where('partner_company_id', (int) $user->partner_company_id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Con permiso «Estado de cuenta» y si el aliado maneja línea de crédito.
     */
    private static function currentPartnerHasAssignedCredit(): bool
    {
        return once(fn (): bool => PartnerPanel::can(PartnerPanelPermission::Account)
            && PartnerCompany::query()
                ->whereKey(PartnerPanel::companyId())
                ->whereNotNull('assigned_credit_limit')
                ->exists());
    }

    private static function recordBelongsToCurrentPartnerCompany(Model $record): bool
    {
        if (! $record instanceof HistoricalOfMovement) {
            return false;
        }

        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->isPartnerCompanyUser()) {
            return false;
        }

        return (int) $record->partner_company_id === (int) $user->partner_company_id;
    }

    public static function form(Schema $schema): Schema
    {
        return HistoricalOfMovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HistoricalOfMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HistoricalOfMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistoricalOfMovements::route('/'),
            'view' => ViewHistoricalOfMovement::route('/{record}'),
        ];
    }
}
