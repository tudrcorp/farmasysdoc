<?php

namespace App\Filament\Resources\AccountsReceivables\Tables;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\AccountsReceivable;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsReceivableStatus;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountsReceivablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['sale', 'branch', 'client']))
            ->columns([
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => AccountsReceivableStatus::label($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        AccountsReceivableStatus::COBRADO => 'success',
                        AccountsReceivableStatus::CANCELADO => 'gray',
                        default => 'warning',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('sale_number_snapshot')
                    ->label('Nº venta')
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::ShoppingBag)
                    ->iconColor('gray')
                    ->url(fn (AccountsReceivable $record): ?string => $record->sale_id
                        ? SaleResource::getUrl('view', ['record' => $record->sale_id], isAbsolute: false)
                        : null),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingStorefront)
                    ->url(fn (AccountsReceivable $record): ?string => $record->branch_id
                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                        : null),
                TextColumn::make('client_name_snapshot')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->icon(Heroicon::User)
                    ->iconColor('gray'),
                TextColumn::make('client_document_snapshot')
                    ->label('Documento')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('issued_at')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('sale_total_usd')
                    ->label('Total venta (USD)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                TextColumn::make('remaining_principal_usd')
                    ->label('Saldo (USD)')
                    ->alignEnd()
                    ->weight('medium')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                TextColumn::make('original_balance_ves')
                    ->label('Saldo ref. (Bs)')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('current_balance_ves')
                    ->label('Saldo actual (Bs)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
            ])
            ->defaultSort('issued_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
            ])
            ->striped()
            ->emptyStateHeading('Sin cuentas por cobrar')
            ->emptyStateDescription('Las ventas a crédito registradas desde la caja aparecerán aquí.')
            ->emptyStateIcon(Heroicon::ArrowTrendingUp);
    }

    private static function formatBs(float $value): string
    {
        return 'Bs. '.number_format($value, 2, ',', '.');
    }
}
