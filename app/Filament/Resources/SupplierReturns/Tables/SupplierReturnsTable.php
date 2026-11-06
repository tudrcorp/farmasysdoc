<?php

namespace App\Filament\Resources\SupplierReturns\Tables;

use App\Enums\SupplierReturnStatus;
use App\Filament\Resources\SupplierReturns\Actions\SupplierReturnActions;
use App\Filament\Resources\SupplierReturns\Schemas\SupplierReturnForm;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\Purchases\SupplierReturnNotifier;
use App\Services\Purchases\SupplierReturnWorkflow;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupplierReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['branch:id,name', 'supplier:id,legal_name,trade_name', 'purchase:id,purchase_number', 'creator:id,name'])
                ->withCount('items')
                ->withSum('items', 'quantity_excess'))
            ->columns([
                TextColumn::make('number')->label('Número')->badge()->color('primary')->searchable()->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (SupplierReturnStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (SupplierReturnStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('supplier.legal_name')
                    ->label('Proveedor')
                    ->formatStateUsing(fn (SupplierReturn $record): string => $record->supplierName())
                    ->searchable(['legal_name', 'trade_name'])
                    ->wrap(),
                TextColumn::make('branch.name')->label('Sucursal')->sortable(),
                TextColumn::make('items_count')
                    ->label('Productos')
                    ->alignCenter()
                    ->description(fn (SupplierReturn $record): string => SupplierReturnNotifier::quantity((float) $record->items_sum_quantity_excess).' u. de más'),
                TextColumn::make('purchase.purchase_number')->label('Compra')->placeholder('—')->toggleable(),
                TextColumn::make('received_on')->label('Recibida')->date('d/m/Y')->sortable(),
                TextColumn::make('created_at')
                    ->label('Registrada')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->description(fn (SupplierReturn $record): ?string => $record->creator?->name)
                    ->sortable(),
                TextColumn::make('returned_on')->label('Entregada')->date('d/m/Y')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->filters([
                SelectFilter::make('branch_id')->label('Sucursal')->options(fn (): array => SupplierReturnForm::branchOptions()),
                SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->options(fn (): array => Supplier::query()->orderBy('legal_name')->pluck('legal_name', 'id')->all())
                    ->searchable(),
                Filter::make('received_between')
                    ->schema([
                        DatePicker::make('from')->label('Recibida desde')->native(false),
                        DatePicker::make('until')->label('Recibida hasta')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('received_on', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('received_on', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                EditAction::make()
                    ->visible(fn (SupplierReturn $record): bool => ($user = Auth::user()) instanceof User && app(SupplierReturnWorkflow::class)->canEdit($user, $record)),
                ActionGroup::make(SupplierReturnActions::all()),
            ])
            ->emptyStateHeading('Sin devoluciones')
            ->emptyStateDescription('Registre aquí la mercancía que llegue de más para devolverla al proveedor.')
            ->emptyStateIcon(Heroicon::ArrowUturnLeft);
    }
}
