<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Enums\PurchaseEntryCurrency;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\DefaultVatRate;
use App\Support\Purchases\LotExpirationMonthYear;
use App\Support\Purchases\PurchaseDocumentTotals;
use App\Support\Purchases\PurchaseEntryCurrencySwitcher;
use App\Support\Purchases\PurchasePaymentStatus;
use Filament\Forms\Components\CheckboxList;
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
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación de la compra')
                    ->description('Primero elija el proveedor; el N° de factura y el N° de control deben ser únicos para ese proveedor (otro proveedor puede usar el mismo número). La orden interna OC-… se genera al guardar.')
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('supplier_id')
                                    ->label('RIF del proveedor')
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
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        $set('supplier_display_name', self::supplierDisplayNameForSupplierId($state));
                                    })
                                    ->helperText('Paso 1: identifique al proveedor. Así se valida que la factura no esté duplicada para él.')
                                    ->prefixIcon(Heroicon::FingerPrint),
                                TextInput::make('supplier_display_name')
                                    ->label('Nombre del proveedor')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('—')
                                    ->prefixIcon(Heroicon::Truck),
                            ]),
                        Placeholder::make('purchase_number_notice')
                            ->label('Número de orden de compra')
                            ->helperText(
                                'Se asigna solo al guardar: OC-, año de registro, guion e ID interno con cuatro dígitos (ej. OC-'.date('Y').'-0042).',
                            )
                            ->content('Se generará al guardar la compra.')
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('supplier_invoice_number')
                                    ->label('N° de factura')
                                    ->placeholder(fn (Get $get): string => filled($get('supplier_id'))
                                        ? 'Según factura del proveedor'
                                        : 'Primero seleccione el proveedor')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::DocumentText)
                                    ->required()
                                    ->disabled(fn (Get $get): bool => blank($get('supplier_id')))
                                    ->helperText('Único por proveedor: no puede repetirse para el mismo RIF/proveedor.')
                                    ->rules([
                                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                            $supplierId = $get('supplier_id');
                                            if (blank($supplierId)) {
                                                return;
                                            }
                                            $normalized = trim((string) $value);
                                            if ($normalized === '') {
                                                return;
                                            }
                                            if (Purchase::query()
                                                ->where('supplier_id', (int) $supplierId)
                                                ->where('supplier_invoice_number', $normalized)
                                                ->exists()) {
                                                $fail('Ya existe una compra con este número de factura para el proveedor seleccionado.');
                                            }
                                        },
                                    ]),
                                TextInput::make('supplier_control_number')
                                    ->label('N° de control')
                                    ->placeholder(fn (Get $get): string => filled($get('supplier_id'))
                                        ? 'Según factura'
                                        : 'Primero seleccione el proveedor')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::FingerPrint)
                                    ->disabled(fn (Get $get): bool => blank($get('supplier_id')))
                                    ->helperText('Si lo indica, debe ser único para el mismo proveedor (no puede coincidir con otra compra de ese proveedor).')
                                    ->rules([
                                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                            $supplierId = $get('supplier_id');
                                            if (blank($supplierId)) {
                                                return;
                                            }
                                            $normalized = trim((string) $value);
                                            if ($normalized === '') {
                                                return;
                                            }
                                            if (Purchase::query()
                                                ->where('supplier_id', (int) $supplierId)
                                                ->where('supplier_control_number', $normalized)
                                                ->exists()) {
                                                $fail('Ya existe una compra con este número de control para el proveedor seleccionado.');
                                            }
                                        },
                                    ]),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                DatePicker::make('supplier_invoice_date')
                                    ->label('Fecha de la factura')
                                    ->helperText('Fecha impresa en la factura del proveedor.')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->prefixIcon(Heroicon::CalendarDays),
                                DatePicker::make('registered_in_system_date')
                                    ->label('Fecha de carga en el sistema')
                                    ->helperText('Fecha en que registras esta compra en el sistema.')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Clock),
                            ]),
                        CheckboxList::make('entry_currency_selection')
                            ->label('Moneda de la factura')
                            ->helperText('Marque solo una opción. En VES los importes se guardan en bolívares según la factura; el inventario usa USD con la tasa oficial (promedio) de la fecha de la factura.')
                            ->options(PurchaseEntryCurrency::checkboxOptions())
                            ->default([PurchaseEntryCurrency::USD->value])
                            ->columns(2)
                            ->live()
                            ->minItems(1)
                            ->maxItems(1)
                            ->required()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (?array $state, Set $set): void {
                                $code = (is_array($state) && $state !== [])
                                    ? (string) reset($state)
                                    : PurchaseEntryCurrency::USD->value;
                                if (! in_array($code, [PurchaseEntryCurrency::USD->value, PurchaseEntryCurrency::VES->value], true)) {
                                    $code = PurchaseEntryCurrency::USD->value;
                                }
                                $set('/data.entry_currency', $code, isAbsolute: true);
                            })
                            ->afterStateUpdated(function (?array $state, Set $set, Component $livewire): void {
                                if (! property_exists($livewire, 'data') || ! is_array($livewire->data)) {
                                    return;
                                }

                                $raw = is_array($state) ? array_values(array_filter($state)) : [];
                                if (count($raw) > 1) {
                                    $last = (string) $raw[count($raw) - 1];
                                    $set('/data.entry_currency_selection', [$last], isAbsolute: true);
                                    $raw = [$last];
                                }
                                $code = $raw !== [] ? (string) $raw[0] : PurchaseEntryCurrency::USD->value;
                                if (! in_array($code, [PurchaseEntryCurrency::USD->value, PurchaseEntryCurrency::VES->value], true)) {
                                    $code = PurchaseEntryCurrency::USD->value;
                                }

                                $previous = (string) ($livewire->data['entry_currency'] ?? PurchaseEntryCurrency::USD->value);

                                $needsRate = ($code === PurchaseEntryCurrency::VES->value && $previous === PurchaseEntryCurrency::USD->value)
                                    || ($code === PurchaseEntryCurrency::USD->value && $previous === PurchaseEntryCurrency::VES->value);
                                if ($needsRate && $previous !== $code) {
                                    $rate = app(VenezuelaOfficialUsdVesRateClient::class)
                                        ->rateForDate($livewire->data['supplier_invoice_date'] ?? null);
                                    if ($rate === null || $rate <= 0) {
                                        Notification::make()
                                            ->title('Sin tasa oficial Bs/USD')
                                            ->body('No hay promedio oficial para la fecha de la factura; no se puede cambiar la moneda. Corrija la fecha o intente más tarde.')
                                            ->danger()
                                            ->send();
                                        $set('/data.entry_currency_selection', [$previous], isAbsolute: true);
                                        $set('/data.entry_currency', $previous, isAbsolute: true);

                                        return;
                                    }
                                }

                                if ($previous !== $code) {
                                    $adjusted = PurchaseEntryCurrencySwitcher::computeAdjustedItemsAndHeader($livewire->data, $previous, $code);
                                    if ($adjusted !== null) {
                                        [$items, $header] = $adjusted;
                                        $set('/data.items', array_values($items), isAbsolute: true);
                                        foreach ($header as $headerKey => $headerValue) {
                                            $set('/data.'.$headerKey, $headerValue, isAbsolute: true);
                                        }
                                    }
                                }

                                $set('/data.entry_currency', $code, isAbsolute: true);
                            })
                            ->columnSpanFull(),
                        Hidden::make('entry_currency')
                            ->default(PurchaseEntryCurrency::USD->value)
                            ->dehydrated(true),
                        Select::make('branch_id')
                            ->label('Sucursal de recepción')
                            ->relationship(
                                name: 'branch',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $query->where('is_active', true)->orderBy('name');

                                    return BranchAuthScope::applyToBranchFormSelect($query);
                                },
                            )
                            ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->helperText('Donde ingresa o registra la mercancía.')
                            ->prefixIcon(Heroicon::BuildingStorefront)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Líneas de compra')
                    ->description('Busque por nombre o código de barras y pulse Enter para añadir una fila. El descuento % se toma del producto (ajustable en la línea). El IVA lo define el catálogo (Grava IVA) y la tasa global: primero se aplica el descuento al subtotal bruto y el IVA corre sobre la base resultante. Los productos marcados como “requieren vencimiento en compras” muestran la columna de lote (mm/AAAA) y al guardar se registran en la tabla de lotes con el N° de factura.')
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
                                TableColumn::make('Nombre · código')->width('20%'),
                                TableColumn::make('Costo'),
                                TableColumn::make('Desc. %'),
                                TableColumn::make('IVA'),
                                TableColumn::make('Cant.'),
                                TableColumn::make('Venc. (mm/AAAA)')->width('7rem'),
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
                                Hidden::make('line_vat_percent')
                                    ->default(0)
                                    ->dehydrated(true),
                                Hidden::make('line_total')
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
                                    ->rule('decimal:0,2')
                                    ->prefix(fn (Get $get): string => self::currencyPrefixForFormGet($get))
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
                                    // ->helperText('Viene del % de descuento del producto; puede corregirse según la factura del proveedor.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        self::syncPurchaseLineItemComputedAmounts($set, $get);
                                    }),
                                TextInput::make('tax_amount')
                                    ->label('IVA')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => self::currencyPrefixForFormGet($get))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0),
                                // ->helperText('Importe de IVA sobre la base neta (tras descuento), según el producto y la tasa global.'),
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
                                TextInput::make('lot_expiration_month_year')
                                    ->label('Venc.')
                                    ->placeholder(fn (Get $get): string => self::purchaseLineProductRequiresExpiry($get('product_id'))
                                        ? '08/2026'
                                        : '—')
                                    ->maxLength(7)
                                    ->autocomplete(false)
                                    // En modo tabla, visible(false) deja la celda con clase fi-hidden y suele “desaparecer”.
                                    // Siempre mostramos la celda; solo editables los productos marcados con lote en catálogo.
                                    ->disabled(fn (Get $get): bool => ! self::purchaseLineProductRequiresExpiry($get('product_id')))
                                    ->dehydrated(true)
                                    ->required(fn (Get $get): bool => self::purchaseLineProductRequiresExpiry($get('product_id')))
                                    // ->helperText('Solo aplica si el producto tiene “Requiere vencimiento de lote en compras”.')
                                    ->live(onBlur: true)
                                    ->rules([
                                        function (Get $get): \Closure {
                                            return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                                $needs = self::purchaseLineProductRequiresExpiry($get('product_id'));
                                                $normalized = LotExpirationMonthYear::normalize($value);
                                                if ($needs && $normalized === null) {
                                                    $fail('Indique el vencimiento del lote (mm/AAAA).');

                                                    return;
                                                }
                                                if ($normalized !== null && ! LotExpirationMonthYear::isValidFormat($normalized)) {
                                                    $fail('Use el formato mm/AAAA (ej. 08/2026).');
                                                }
                                            };
                                        },
                                    ]),
                                Placeholder::make('line_total_display')
                                    ->label('Total')
                                    ->content(fn (Get $get): string => self::formatPurchaseLineVisualTotal($get)),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::finalizePurchaseItemRow($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Model $record): array => self::finalizePurchaseItemRow($data))
                            ->columnSpanFull(),
                        TextInput::make('declared_invoice_total')
                            ->label('Total de la factura (según proveedor)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->prefix(fn (Get $get): string => self::currencyPrefixForFormGet($get))
                            ->helperText('Debe alinearse con el total calculado por líneas: solo se admiten diferencias inferiores a 1,00 en la parte decimal (p. ej. ,56 vs ,80 sí; ,56 vs ,56 de un entero distinto no).')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Totales')
                    ->description('El descuento de subtotal es un % sobre el subtotal de líneas (use 0 si no aplica). El IVA se calcula al '.DefaultVatRate::percent().'% sobre la base imponible.')
                    ->icon(Heroicon::Calculator)
                    ->compact()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 6,
                        ])
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix(fn (Get $get): string => self::currencyPrefixForFormGet($get))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-end tabular-nums']),
                                TextInput::make('document_discount_percent')
                                    ->label('Descuento de subtotal')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->suffix('%')
                                    ->helperText('Ingrese 0 si no aplica.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        self::applyPurchaseDocumentTotalsFromItems($set, $get);
                                    })
                                    ->extraInputAttributes(['class' => 'text-end tabular-nums']),
                                TextInput::make('net_exempt_after_document_discount')
                                    ->label('Base (productos sin IVA)')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => self::currencyPrefixForFormGet($get))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0)
                                    ->extraInputAttributes(['class' => 'text-end tabular-nums']),
                                TextInput::make('net_taxable_after_document_discount')
                                    ->label('Base imponible')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => self::currencyPrefixForFormGet($get))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0)
                                    ->extraInputAttributes(['class' => 'text-end tabular-nums']),
                                TextInput::make('tax_total')
                                    ->label('Cálculo del IVA')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix(fn (Get $get): string => self::currencyPrefixForFormGet($get))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-end tabular-nums']),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->prefix(fn (Get $get): string => self::currencyPrefixForFormGet($get))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-end text-base font-semibold tabular-nums lg:text-lg']),
                            ]),
                        Hidden::make('subtotal_exempt_amount')->default(0)->dehydrated(true),
                        Hidden::make('subtotal_taxable_amount')->default(0)->dehydrated(true),
                        Hidden::make('discount_total')->default(0)->dehydrated(true),
                        Hidden::make('document_discount_amount')->default(0)->dehydrated(true),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Facturación y pago al proveedor')
                    ->description('Estado del pago al proveedor.')
                    ->icon(Heroicon::DocumentCurrencyDollar)
                    ->schema([
                        Select::make('payment_status')
                            ->label('Estado del pago')
                            ->options(PurchasePaymentStatus::options())
                            ->default(PurchasePaymentStatus::PAGADO_CONTADO)
                            ->required()
                            ->native(false)
                            ->helperText('Solo se admiten los estados definidos para auditoría y reportes.')
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
        $vatPercent = self::purchaseLineVatPercentForProductId($get('product_id'));
        $set('line_vat_percent', round($vatPercent, 2));

        $unitCost = round(max(0.0, (float) ($get('unit_cost') ?? 0)), 2);
        $set('unit_cost', $unitCost);

        $amounts = PurchaseDocumentTotals::lineAmounts([
            'quantity_ordered' => $get('quantity_ordered'),
            'unit_cost' => $unitCost,
            'line_discount_percent' => $get('line_discount_percent'),
            'line_vat_percent' => $vatPercent,
        ]);

        $set('line_subtotal', $amounts['line_subtotal']);
        $set('tax_amount', $amounts['tax_amount']);
        $set('line_total', $amounts['line_total']);

        self::applyPurchaseDocumentTotalsFromItems($set, $get);
    }

    public static function formatPurchaseLineVisualTotal(Get $get): string
    {
        $qty = (float) ($get('quantity_ordered') ?? 0);
        $cost = (float) ($get('unit_cost') ?? 0);
        $total = round($qty * $cost, 2);

        return self::currencyPrefixForFormGet($get).number_format($total, 2, '.', ',');
    }

    public static function currencyPrefixForFormGet(Get $get): string
    {
        $v = (string) ($get('/data.entry_currency', true) ?? PurchaseEntryCurrency::USD->value);

        return PurchaseEntryCurrency::tryFrom($v)?->moneyPrefix() ?? '$';
    }

    /**
     * Tasa de IVA de la línea según el producto (catálogo); 0 si no grava IVA.
     */
    public static function purchaseLineVatPercentForProductId(mixed $productId): float
    {
        if (blank($productId)) {
            return 0.0;
        }

        $product = Product::query()->find((int) $productId);
        if (! $product instanceof Product || ! $product->applies_vat) {
            return 0.0;
        }

        return DefaultVatRate::percent();
    }

    public static function applyPurchaseDocumentTotalsFromItems(Set $set, Get $get): void
    {
        $items = $get('/data.items');
        if (! is_array($items)) {
            $items = [];
        }

        $docDisc = (float) ($get('/data.document_discount_percent') ?? 0);
        $header = PurchaseDocumentTotals::documentHeaderWithDocumentDiscount($items, $docDisc);

        foreach ($header as $key => $value) {
            $set('data.'.$key, $value, true);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function finalizePurchaseItemRow(array $data): array
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $product = null;
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
                $data['line_vat_percent'] = $product->applies_vat ? DefaultVatRate::percent() : 0.0;
            }
        } else {
            $data['line_vat_percent'] = 0.0;
        }

        $data['lot_expiration_month_year'] = LotExpirationMonthYear::normalize($data['lot_expiration_month_year'] ?? null);
        if (! $product instanceof Product || ! $product->requires_expiry_on_purchase) {
            $data['lot_expiration_month_year'] = null;
        }

        $data['unit_cost'] = round(max(0.0, (float) ($data['unit_cost'] ?? 0)), 2);

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
                ->orderByRaw('CASE WHEN tax_id IS NULL OR tax_id = "" THEN 1 ELSE 0 END')
                ->orderBy('tax_id')
                ->orderBy('legal_name')
                ->limit(25)
                ->get()
                ->mapWithKeys(fn (Supplier $supplier): array => [
                    $supplier->getKey() => self::formatSupplierRifSelectLabel($supplier),
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
            ->orderByRaw('CASE WHEN tax_id IS NULL OR tax_id = "" THEN 1 ELSE 0 END')
            ->orderBy('tax_id')
            ->orderBy('legal_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Supplier $supplier): array => [
                $supplier->getKey() => self::formatSupplierRifSelectLabel($supplier),
            ])
            ->all();
    }

    private static function supplierOptionLabelForPurchaseForm(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $supplier = Supplier::query()->find((int) $value);

        return $supplier instanceof Supplier ? self::formatSupplierRifSelectLabel($supplier) : null;
    }

    /**
     * Etiqueta del select: RIF primero; sin RIF se muestra código interno y nombre.
     */
    private static function formatSupplierRifSelectLabel(Supplier $supplier): string
    {
        $name = $supplier->displayName();

        if (filled($supplier->tax_id)) {
            return trim((string) $supplier->tax_id).' — '.$name;
        }

        $code = filled($supplier->code)
            ? (string) $supplier->code
            : Supplier::formatCode($supplier->getKey());

        return 'Sin RIF · '.$code.' — '.$name;
    }

    public static function supplierDisplayNameForSupplierId(mixed $supplierId): string
    {
        if (blank($supplierId)) {
            return '';
        }

        $supplier = Supplier::query()->find((int) $supplierId);

        return $supplier instanceof Supplier ? $supplier->displayName() : '';
    }

    private static function purchaseLineProductRequiresExpiry(mixed $productId): bool
    {
        if (blank($productId)) {
            return false;
        }

        $product = Product::query()->find((int) $productId);

        return $product instanceof Product && $product->requires_expiry_on_purchase;
    }
}
