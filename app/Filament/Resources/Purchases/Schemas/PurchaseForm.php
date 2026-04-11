<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Product;
use App\Models\Supplier;
use App\Support\Purchases\PurchaseDocumentTotals;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación de la compra')
                    ->description('Datos del documento del proveedor, referencia interna, proveedor y sucursal de recepción.')
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->schema([
                        TextInput::make('purchase_number')
                            ->label('Número de orden de compra')
                            ->placeholder('Ej. OC-2026-0001')
                            ->helperText('Referencia única para trazabilidad con el proveedor y contabilidad.')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->prefixIcon(Heroicon::Hashtag)
                            ->autocomplete('off')
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextInput::make('supplier_invoice_number')
                                    ->label('N° de factura')
                                    ->placeholder('Según factura del proveedor')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::DocumentText),
                                TextInput::make('supplier_control_number')
                                    ->label('N° de control')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::FingerPrint),
                                DatePicker::make('supplier_invoice_date')
                                    ->label('Fecha de la factura')
                                    ->native(false)
                                    ->prefixIcon(Heroicon::CalendarDays),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('supplier_id')
                                    ->label('Proveedor')
                                    ->searchable()
                                    ->searchDebounce(300)
                                    ->getSearchResultsUsing(
                                        fn (string $search): array => self::searchSuppliersForPurchaseForm($search),
                                    )
                                    ->getOptionLabelUsing(
                                        fn ($value): ?string => self::supplierOptionLabelForPurchaseForm($value),
                                    )
                                    ->native(false)
                                    ->required()
                                    ->helperText('Busque por nombre comercial, razón social, código interno (PROV-…) o RIF / NIT.')
                                    ->prefixIcon(Heroicon::Truck),
                                Select::make('branch_id')
                                    ->label('Sucursal de recepción')
                                    ->relationship(
                                        name: 'branch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText('Donde ingresa o registra la mercancía.')
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Líneas de compra')
                    ->description('Busque por nombre o código de barras y pulse Enter para añadir una fila. Si el producto no existe, se abrirá el alta rápida. Los totales inferiores se recalculan desde las líneas.')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        TextInput::make('purchase_line_product_search')
                            ->label('Buscar producto')
                            ->placeholder('Nombre o código de barras — Enter para añadir')
                            ->prefixIcon(Heroicon::MagnifyingGlass)
                            ->columnSpanFull()
                            ->dehydrated(false)
                            ->extraInputAttributes([
                                // Livewire evita que Enter envíe el formulario principal; sin .prevent el submit gana a Alpine.
                                'wire:keydown.enter.prevent' => 'addPurchaseLineFromSearch($event.target.value)',
                            ]),
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->saveRelationshipsWhenHidden(false)
                            ->defaultItems(0)
                            ->addable(false)
                            ->reorderable(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                self::applyPurchaseDocumentTotalsFromItems($set, $get);
                            })
                            ->table([
                                TableColumn::make('Nombre · código')->width('24%'),
                                TableColumn::make('Costo'),
                                TableColumn::make('Desc. %'),
                                TableColumn::make('IVA %'),
                                TableColumn::make('Cant.'),
                                TableColumn::make('Total'),
                            ])
                            ->schema([
                                Hidden::make('product_id')
                                    ->required(),
                                Hidden::make('product_name_snapshot'),
                                Hidden::make('sku_snapshot'),
                                Hidden::make('quantity_received')
                                    ->dehydrated(true),
                                Hidden::make('line_subtotal')
                                    ->default(0)
                                    ->dehydrated(true),
                                Hidden::make('tax_amount')
                                    ->default(0)
                                    ->dehydrated(true),
                                Placeholder::make('product_line_label')
                                    ->label('Nombre · código')
                                    ->content(fn (Get $get): string => self::formatPurchaseLineProductLabel($get)),
                                TextInput::make('unit_cost')
                                    ->label('Costo')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->prefix('$')
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        self::syncPurchaseLineItemComputedAmounts($set, $get);
                                    }),
                                TextInput::make('line_discount_percent')
                                    ->label('Desc. %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        self::syncPurchaseLineItemComputedAmounts($set, $get);
                                    }),
                                TextInput::make('line_vat_percent')
                                    ->label('IVA %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        self::syncPurchaseLineItemComputedAmounts($set, $get);
                                    }),
                                TextInput::make('quantity_ordered')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        self::syncPurchaseLineItemComputedAmounts($set, $get);
                                    }),
                                TextInput::make('line_total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::finalizePurchaseItemRow($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Model $record): array => self::finalizePurchaseItemRow($data))
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Totales')
                    ->description('Montos calculados automáticamente a partir de las líneas de compra (solo lectura).')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(true),
                                TextInput::make('tax_total')
                                    ->label('Impuestos')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(true),
                                TextInput::make('discount_total')
                                    ->label('Descuentos')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(true),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(true),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Facturación y pago al proveedor')
                    ->description('Estado del pago al proveedor.')
                    ->icon(Heroicon::DocumentCurrencyDollar)
                    ->schema([
                        TextInput::make('payment_status')
                            ->label('Estado del pago')
                            ->placeholder('Ej. pendiente, parcial, pagado')
                            ->maxLength(100)
                            ->helperText('Texto libre o código interno de tesorería.')
                            ->prefixIcon(Heroicon::CreditCard)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Condiciones comerciales, incidencias o acuerdos con el proveedor.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Lotes, garantías, diferencias en recepción…')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function findProductForPurchaseLineSearch(string $term): ?Product
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $base = Product::query()->where('is_active', true);

        $byBarcode = (clone $base)->where('barcode', $term)->first();
        if ($byBarcode instanceof Product) {
            return $byBarcode;
        }

        $bySku = (clone $base)->where('sku', $term)->first();
        if ($bySku instanceof Product) {
            return $bySku;
        }

        $lower = mb_strtolower($term);
        $byExactName = (clone $base)->whereRaw('LOWER(name) = ?', [$lower])->first();
        if ($byExactName instanceof Product) {
            return $byExactName;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return (clone $base)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->first();
    }

    public static function formatPurchaseLineProductLabel(Get $get): string
    {
        $name = trim((string) $get('product_name_snapshot'));
        $code = trim((string) $get('sku_snapshot'));

        if ($name !== '' && $code !== '') {
            return $name.' · '.$code;
        }

        return $name !== '' ? $name : $code;
    }

    public static function syncPurchaseLineItemComputedAmounts(Set $set, Get $get): void
    {
        $amounts = PurchaseDocumentTotals::lineAmounts([
            'quantity_ordered' => $get('quantity_ordered'),
            'unit_cost' => $get('unit_cost'),
            'line_discount_percent' => $get('line_discount_percent'),
            'line_vat_percent' => $get('line_vat_percent'),
        ]);

        $set('line_subtotal', $amounts['line_subtotal']);
        $set('tax_amount', $amounts['tax_amount']);
        $set('line_total', $amounts['line_total']);
    }

    public static function applyPurchaseDocumentTotalsFromItems(Set $set, Get $get): void
    {
        $items = $get('items');
        if (! is_array($items)) {
            $items = [];
        }

        $totals = PurchaseDocumentTotals::documentTotals($items);
        $set('subtotal', $totals['subtotal']);
        $set('tax_total', $totals['tax_total']);
        $set('discount_total', $totals['discount_total']);
        $set('total', $totals['total']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function finalizePurchaseItemRow(array $data): array
    {
        $productId = (int) ($data['product_id'] ?? 0);
        if ($productId > 0) {
            $product = Product::query()->find($productId);
            if ($product instanceof Product) {
                if (blank($data['product_name_snapshot'] ?? null)) {
                    $data['product_name_snapshot'] = $product->name;
                }
                if (blank($data['sku_snapshot'] ?? null)) {
                    $data['sku_snapshot'] = filled($product->barcode)
                        ? (string) $product->barcode
                        : (string) $product->sku;
                }
            }
        }

        $computed = PurchaseDocumentTotals::lineAmounts($data);
        $data['line_subtotal'] = $computed['line_subtotal'];
        $data['tax_amount'] = $computed['tax_amount'];
        $data['line_total'] = $computed['line_total'];
        $data['quantity_received'] = isset($data['quantity_received']) ? (float) $data['quantity_received'] : 0.0;

        return $data;
    }

    /**
     * @return array<int|string, string>
     */
    private static function searchSuppliersForPurchaseForm(string $search): array
    {
        $term = trim($search);
        $query = Supplier::query()->where('is_active', true);

        if ($term === '') {
            return $query
                ->orderBy('legal_name')
                ->limit(25)
                ->get()
                ->mapWithKeys(fn (Supplier $supplier): array => [
                    $supplier->getKey() => self::formatSupplierSelectLabel($supplier),
                ])
                ->all();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $compact = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $term));

        $query->where(function ($q) use ($like, $compact): void {
            $q->where('legal_name', 'like', $like)
                ->orWhere('trade_name', 'like', $like)
                ->orWhere('tax_id', 'like', $like)
                ->orWhere('code', 'like', $like);

            if (strlen($compact) >= 2) {
                $q->orWhere('tax_id', 'like', '%'.$compact.'%');
            }
        });

        return $query
            ->orderBy('legal_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Supplier $supplier): array => [
                $supplier->getKey() => self::formatSupplierSelectLabel($supplier),
            ])
            ->all();
    }

    private static function supplierOptionLabelForPurchaseForm(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $supplier = Supplier::query()->find((int) $value);

        return $supplier instanceof Supplier ? self::formatSupplierSelectLabel($supplier) : null;
    }

    private static function formatSupplierSelectLabel(Supplier $supplier): string
    {
        $name = filled($supplier->trade_name)
            ? (string) $supplier->trade_name
            : (string) $supplier->legal_name;

        $suffix = [];
        if (filled($supplier->tax_id)) {
            $suffix[] = (string) $supplier->tax_id;
        }
        if (filled($supplier->code)) {
            $suffix[] = (string) $supplier->code;
        }

        if ($suffix === []) {
            return $name;
        }

        return $name.' · '.implode(' · ', $suffix);
    }
}
