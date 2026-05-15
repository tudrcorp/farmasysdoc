<?php

namespace App\Filament\Resources\PhysicalCashBoxMovements;

use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\PhysicalCashBoxMovements\Pages\ManagePhysicalCashBoxMovements;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxMovement;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PhysicalCashBoxMovementResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = PhysicalCashBoxMovement::class;

    protected static ?string $navigationLabel = 'Movimientos caja física';

    protected static ?string $modelLabel = 'Movimiento de caja física';

    protected static ?string $pluralModelLabel = 'Movimientos de caja física';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 9;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowsRightLeft;

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

        if (! $user->isAdministrator() && ! $user->isCashier() && ! $user->hasGerenciaRole()) {
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
     * @return Builder<PhysicalCashBoxMovement>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['physicalCashBox.user', 'sale']);

        $user = Auth::user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isCashier() && ! $user->isAdministrator() && ! $user->hasGerenciaRole()) {
            return $query->whereHas(
                'physicalCashBox',
                fn (Builder $q): Builder => $q->where('user_id', $user->id),
            );
        }

        if ($user->hasGerenciaRole() && ! $user->isAdministrator()) {
            $branchIds = $user->restrictedBranchIdsForQueries();
            if ($branchIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas(
                'sale',
                fn (Builder $q): Builder => $q->whereIn('sales.branch_id', $branchIds),
            );
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sale.sale_number')
                    ->label('Venta'),
                TextEntry::make('physicalCashBox.user.name')
                    ->label('Cajero'),
                TextEntry::make('kind')
                    ->label('Tipo'),
                TextEntry::make('client_bill_usd')
                    ->label('Billete cliente (USD)')
                    ->numeric(decimalPlaces: 2),
                TextEntry::make('document_total_usd')
                    ->label('Total venta (USD)')
                    ->numeric(decimalPlaces: 2),
                TextEntry::make('change_on_bill_usd')
                    ->label('Vuelto sobre billete (USD)')
                    ->numeric(decimalPlaces: 2),
                TextEntry::make('change_on_bill_ves')
                    ->label('Equivalente VES (sobre billete)')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—'),
                TextEntry::make('drawer_out_usd')
                    ->label('USD retirados de caja')
                    ->numeric(decimalPlaces: 2),
                TextEntry::make('final_change_usd')
                    ->label('Vuelto restante (USD)')
                    ->numeric(decimalPlaces: 2),
                TextEntry::make('final_change_ves')
                    ->label('Vuelto restante (VES)')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—'),
                TextEntry::make('bcv_ves_per_usd')
                    ->label('Tasa BCV (Bs./USD)')
                    ->numeric(decimalPlaces: 6)
                    ->placeholder('—'),
                TextEntry::make('created_by')
                    ->label('Registrado por')
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('physicalCashBox.user.name')
                    ->label('Cajero')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sale.sale_number')
                    ->label('Venta')
                    ->url(fn (PhysicalCashBoxMovement $record): ?string => $record->sale instanceof Sale
                        ? SaleResource::getUrl('view', ['record' => $record->sale], isAbsolute: false)
                        : null)
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client_bill_usd')
                    ->label('Billete del cliente (USD)')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$'),
                TextColumn::make('drawer_out_usd')
                    ->label('USD retirados de la caja para vueltos')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$'),
                TextColumn::make('final_change_ves')
                    ->label('Vuelto en VES (restante)')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('Bs. ')
                    ->sortable(),
                TextColumn::make('usd_delta_cash_box')
                    ->label('Variación USD en caja')
                    ->state(fn (PhysicalCashBoxMovement $record): float => round(
                        (float) $record->client_bill_usd - (float) $record->drawer_out_usd,
                        2,
                    ))
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->color(fn (PhysicalCashBoxMovement $record): string => ((float) $record->client_bill_usd - (float) $record->drawer_out_usd) >= 0 ? 'success' : 'danger'),
                TextColumn::make('ves_delta_cash_box')
                    ->label('Variación VES en caja')
                    ->state(fn (PhysicalCashBoxMovement $record): float => round(-1 * abs((float) ($record->final_change_ves ?? 0)), 2))
                    ->numeric(decimalPlaces: 2)
                    ->prefix('Bs. ')
                    ->color(fn (PhysicalCashBoxMovement $record): string => ((float) ($record->final_change_ves ?? 0)) > 0.0001 ? 'danger' : 'gray'),
                TextColumn::make('final_change_usd')
                    ->label('USD vuelto restante')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('document_total_usd')
                    ->label('Total cobrado')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('physical_cash_box_id')
                    ->label('Cajero')
                    ->relationship(
                        name: 'physicalCashBox',
                        titleAttribute: 'user_id',
                        modifyQueryUsing: function (Builder $query): Builder {
                            return $query->with('user')->orderBy('user_id');
                        },
                    )
                    ->getOptionLabelFromRecordUsing(function (PhysicalCashBox $record): string {
                        $name = $record->user?->name ?? 'Usuario';

                        return $name.' (#'.$record->user_id.')';
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => Auth::user() instanceof User
                        && (Auth::user()->isAdministrator() || Auth::user()->hasGerenciaRole())),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('Sin movimientos')
            ->emptyStateDescription('Los vueltos en efectivo USD registrados desde la caja aparecerán aquí.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePhysicalCashBoxMovements::route('/'),
        ];
    }
}
