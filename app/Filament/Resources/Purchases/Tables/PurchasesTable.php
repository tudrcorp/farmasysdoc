<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Enums\PurchaseStatus;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['supplier', 'branch'])
                ->withCount('items'))
            ->columns([
                TextColumn::make('purchase_number')
                    ->label('Nº orden')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Número copiado')
                    ->placeholder('—')
                    ->weight('medium')
                    ->icon(Heroicon::Hashtag)
                    ->iconColor('gray'),
                TextColumn::make('supplier_display')
                    ->label('Proveedor')
                    ->state(fn (Purchase $record): string => self::formatSupplierPrimaryName($record))
                    ->description(fn (Purchase $record): ?string => self::formatSupplierSecondaryLine($record))
                    ->searchable(query: function (Builder $query, string $search): void {
                        $like = '%'.addcslashes($search, '%_\\').'%';
                        $query->whereHas('supplier', function (Builder $q) use ($like): void {
                            $q->where('legal_name', 'like', $like)
                                ->orWhere('trade_name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                    })
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Purchase $record): string => self::formatSupplierPrimaryName($record))
                    ->placeholder('—')
                    ->icon(Heroicon::Truck)
                    ->iconColor('gray')
                    ->url(fn (Purchase $record): ?string => $record->supplier_id
                        ? SupplierResource::getUrl('view', ['record' => $record->supplier_id], isAbsolute: false)
                        : null)
                    ->openUrlInNewTab(false),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->description(fn (Purchase $record): ?string => filled($record->branch?->code)
                        ? 'Código: '.$record->branch->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $like = '%'.addcslashes($search, '%_\\').'%';
                        $query->whereHas('branch', function (Builder $q) use ($like): void {
                            $q->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                    })
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->url(fn (Purchase $record): ?string => $record->branch_id
                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                        : null)
                    ->openUrlInNewTab(false),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (PurchaseStatus|string|null $state): string => self::formatPurchaseStatusLabel($state))
                    ->badge()
                    ->color(fn (PurchaseStatus|string|null $state): string => self::purchaseStatusColor($state))
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Signal)
                    ->iconColor('gray'),
                TextColumn::make('items_count')
                    ->label('Líneas')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('0')
                    ->icon(Heroicon::QueueList)
                    ->iconColor('gray')
                    ->tooltip('Cantidad de ítems en el detalle de la orden'),
                TextColumn::make('ordered_at')
                    ->label('Pedido')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::PaperAirplane)
                    ->iconColor('gray'),
                TextColumn::make('expected_delivery_at')
                    ->label('Entrega est.')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable()
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray'),
                TextColumn::make('received_at')
                    ->label('Recepción')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable()
                    ->icon(Heroicon::CheckCircle)
                    ->iconColor('gray'),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('gray'),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_total')
                    ->label('Impuestos')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount_total')
                    ->label('Descuentos')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_invoice_number')
                    ->label('Nº factura prov.')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Factura copiada')
                    ->toggleable()
                    ->icon(Heroicon::DocumentText)
                    ->iconColor('gray'),
                TextColumn::make('supplier_control_number')
                    ->label('Nº control')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable()
                    ->icon(Heroicon::FingerPrint)
                    ->iconColor('gray'),
                TextColumn::make('supplier_invoice_date')
                    ->label('Fecha factura')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable()
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray'),
                TextColumn::make('registered_in_system_date')
                    ->label('Fecha carga sistema')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable()
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray'),
                TextColumn::make('payment_status')
                    ->label('Pago al proveedor')
                    ->formatStateUsing(fn (?string $state): string => self::formatPaymentStatusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => self::paymentStatusColor($state))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable()
                    ->icon(Heroicon::CreditCard)
                    ->iconColor('gray'),
                TextColumn::make('created_by')
                    ->label('Registró')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip('Usuario o sistema indicado al crear el registro'),
                TextColumn::make('updated_by')
                    ->label('Últ. edición por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Alta en sistema')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray'),
                TextColumn::make('updated_at')
                    ->label('Últ. cambio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::ArrowPathRoundedSquare)
                    ->iconColor('gray'),
            ])
            ->defaultSort('ordered_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin órdenes de compra')
            ->emptyStateDescription('Cree una compra para gestionar pedidos a proveedores, recepción por sucursal y totales del documento.')
            ->emptyStateIcon(Heroicon::ClipboardDocumentList)
            ->recordAction('viewPurchaseDetail')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PurchaseStatus::options())
                    ->multiple()
                    ->searchable(),
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
                    ->preload()
                    ->multiple(),
                SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->relationship(
                        name: 'supplier',
                        titleAttribute: 'legal_name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('legal_name'),
                    )
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(
                        fn (Supplier $record): string => $record->trade_name ?: $record->legal_name,
                    )
                    ->multiple(),
                TernaryFilter::make('received')
                    ->label('Recepción')
                    ->placeholder('Todas')
                    ->trueLabel('Recepción registrada')
                    ->falseLabel('Sin recepción')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('received_at'),
                        false: fn (Builder $query) => $query->whereNull('received_at'),
                    ),
                TernaryFilter::make('has_supplier_invoice')
                    ->label('Factura proveedor')
                    ->placeholder('Todas')
                    ->trueLabel('Con número de factura')
                    ->falseLabel('Sin factura')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('supplier_invoice_number')->where('supplier_invoice_number', '!=', ''),
                        false: fn (Builder $query) => $query->where(function (Builder $q): void {
                            $q->whereNull('supplier_invoice_number')->orWhere('supplier_invoice_number', '=', '');
                        }),
                    ),
            ])
            ->recordActions([
                self::viewPurchaseDetailAction(),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ]);
    }

    private static function viewPurchaseDetailAction(): Action
    {
        return Action::make('viewPurchaseDetail')
            ->label('Ver compra')
            ->icon(Heroicon::Eye)
            ->modalHeading(fn (Purchase $record): string => 'Compra '.(filled($record->purchase_number) ? (string) $record->purchase_number : '—'))
            ->modalDescription('Documento de compra y líneas registradas. Las compras guardadas no se pueden modificar.')
            ->modalIcon(Heroicon::ClipboardDocumentList)
            ->modalIconColor('primary')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(function (Purchase $record): View {
                $record->loadMissing([
                    'supplier',
                    'branch',
                    'items' => fn ($query) => $query->orderBy('line_number')->orderBy('id'),
                ]);

                return view('filament.purchases.purchase-detail-modal', [
                    'purchase' => $record,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cerrar')
                ->color('gray'));
    }

    private static function formatSupplierPrimaryName(Purchase $record): string
    {
        $supplier = $record->supplier;
        if (! $supplier) {
            return '—';
        }

        if (filled($supplier->trade_name)) {
            return (string) $supplier->trade_name;
        }

        return filled($supplier->legal_name) ? (string) $supplier->legal_name : '—';
    }

    private static function formatSupplierSecondaryLine(Purchase $record): ?string
    {
        $supplier = $record->supplier;
        if (! $supplier || ! filled($supplier->trade_name) || ! filled($supplier->legal_name)) {
            return null;
        }

        if ($supplier->trade_name === $supplier->legal_name) {
            return null;
        }

        return (string) $supplier->legal_name;
    }

    private static function formatPurchaseStatusLabel(PurchaseStatus|string|null $state): string
    {
        if ($state instanceof PurchaseStatus) {
            return $state->label();
        }

        if ($state === null || $state === '') {
            return '—';
        }

        $enum = PurchaseStatus::tryFrom((string) $state);

        return $enum?->label() ?? (string) $state;
    }

    private static function purchaseStatusColor(PurchaseStatus|string|null $state): string
    {
        $enum = $state instanceof PurchaseStatus
            ? $state
            : PurchaseStatus::tryFrom((string) $state);

        return match ($enum) {
            PurchaseStatus::Draft => 'gray',
            PurchaseStatus::Ordered => 'info',
            PurchaseStatus::PartiallyReceived => 'warning',
            PurchaseStatus::Received => 'success',
            PurchaseStatus::Cancelled => 'danger',
            default => 'gray',
        };
    }

    private static function formatPaymentStatusLabel(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'paid', 'pagado', 'pagada' => 'Pagado',
            'pending', 'pendiente' => 'Pendiente',
            'partial', 'parcial' => 'Parcial',
            default => $value,
        };
    }

    private static function paymentStatusColor(?string $value): string
    {
        if (blank($value)) {
            return 'gray';
        }

        return match (strtolower(trim($value))) {
            'paid', 'pagado', 'pagada' => 'success',
            'pending', 'pendiente' => 'warning',
            'partial', 'parcial' => 'info',
            default => 'gray',
        };
    }
}
