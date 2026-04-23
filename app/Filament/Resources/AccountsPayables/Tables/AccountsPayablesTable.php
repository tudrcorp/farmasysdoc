<?php

namespace App\Filament\Resources\AccountsPayables\Tables;

use App\Filament\Resources\Branches\BranchResource;
use App\Models\AccountsPayable;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountsPayablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['purchase', 'branch']))
            ->columns([
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => AccountsPayableStatus::label($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        AccountsPayableStatus::PAGADO => 'success',
                        default => 'warning',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('purchase.purchase_number')
                    ->label('Nº orden compra')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::ShoppingCart)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingStorefront)
                    ->url(fn (AccountsPayable $record): ?string => $record->branch_id
                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                        : null),
                TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->icon(Heroicon::Truck)
                    ->iconColor('gray'),
                TextColumn::make('supplier_tax_id')
                    ->label('RIF')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('supplier_invoice_number')
                    ->label('Nº factura')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier_control_number')
                    ->label('Nº control')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_at')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('purchase_total_usd')
                    ->label('Total (USD)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                TextColumn::make('purchase_total_ves_at_issue')
                    ->label('Total factura (Bs, tasa emisión)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('original_balance_ves')
                    ->label('Saldo original (Bs)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('current_balance_ves')
                    ->label('Saldo al día (Bs)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                    ->weight('medium'),
                TextColumn::make('last_balance_recalculated_at')
                    ->label('Último recálculo')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(AccountsPayableStatus::options()),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $query->where('is_active', true)->orderBy('name');

                            return BranchAuthScope::applyToBranchFormSelect($query);
                        },
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
