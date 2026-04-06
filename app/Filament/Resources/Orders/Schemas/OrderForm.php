<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderFulfillmentType;
use App\Enums\OrderPartnerCashPaymentMethod;
use App\Enums\OrderPartnerPaymentTerms;
use App\Enums\OrderStatus;
use App\Models\PartnerCompany;
use App\Models\Product;
use App\Models\User;
use App\Support\Orders\OrderTotalsCalculator;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del pedido')
                    ->description('Identificación del pedido, cliente o compañía aliada, sucursal y estado en el flujo de entrega.')
                    ->icon(Heroicon::ShoppingCart)
                    ->schema([
                        Checkbox::make('order_for_partner')
                            ->label('Pedido para una compañía aliada')
                            ->helperText('Si está activado, el pedido se asocia solo al aliado (sin cliente del sistema). Si está desactivado, el pedido es para un cliente registrado (sin aliado en este paso).')
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if (filter_var($state, FILTER_VALIDATE_BOOLEAN)) {
                                    $set('client_id', null);
                                } else {
                                    $set('partner_company_id', null);
                                    self::resetPartnerDeliveryFields($set);
                                }
                            })
                            ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isAdministrator())
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        TextInput::make('order_number')
                            ->label('Número de pedido')
                            ->placeholder('Ej. PED-2026-0001')
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? 'Se asigna automáticamente al guardar (PED-año-000id).'
                                : 'Referencia visible para el cliente y seguimiento. Debe ser única.')
                            ->hidden(fn (string $operation): bool => $operation === 'create')
                            ->required(fn (string $operation): bool => $operation !== 'create')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (string $operation): bool => $operation !== 'create')
                            ->dehydrated(false)
                            ->prefixIcon(Heroicon::Hashtag)
                            ->autocomplete('off')
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('client_id')
                                    ->label('Cliente')
                                    ->relationship(
                                        name: 'client',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('status', 'active')->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->hidden(fn (Get $get): bool => self::clientSelectHidden($get))
                                    ->required(fn (Get $get): bool => self::clientSelectRequired($get))
                                    ->dehydrated(fn (Get $get): bool => self::clientSelectDehydrated($get))
                                    ->prefixIcon(Heroicon::User),
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->relationship(
                                        name: 'branch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Sin sucursal asignada')
                                    ->helperText('Opcional. Sucursal que prepara o despacha el pedido.')
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                                Select::make('partner_company_id')
                                    ->label('Compañía aliada')
                                    ->relationship(
                                        name: 'partnerCompany',
                                        titleAttribute: 'legal_name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('legal_name'),
                                    )
                                    ->getOptionLabelFromRecordUsing(function (PartnerCompany $record): string {
                                        $name = filled($record->trade_name) ? (string) $record->trade_name : (string) $record->legal_name;
                                        $code = filled($record->code) ? ' ('.(string) $record->code.')' : '';

                                        return $name.$code;
                                    })
                                    ->searchable(['legal_name', 'trade_name', 'code', 'tax_id'])
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Elija la compañía aliada')
                                    ->helperText(fn (): string => auth()->user() instanceof User && auth()->user()->isPartnerCompanyUser()
                                        ? 'El pedido queda asociado a su compañía aliada.'
                                        : 'Visible cuando el pedido es para un aliado.')
                                    ->hidden(fn (Get $get): bool => self::partnerSelectHidden($get))
                                    ->required(fn (Get $get): bool => self::partnerSelectRequired($get))
                                    ->dehydrated(fn (Get $get): bool => self::partnerSelectDehydrated($get))
                                    ->default(fn (): ?int => auth()->user() instanceof User && auth()->user()->isPartnerCompanyUser()
                                        ? (int) auth()->user()->partner_company_id
                                        : null)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        $user = auth()->user();
                                        if ($user instanceof User && $user->isAdministrator() && blank($state)) {
                                            self::resetPartnerDeliveryFields($set);
                                        }
                                    })
                                    ->prefixIcon(Heroicon::BuildingOffice2),
                                Select::make('status')
                                    ->label('Estado del pedido')
                                    ->options(OrderStatus::options())
                                    ->native(false)
                                    ->searchable()
                                    ->required()
                                    ->default(OrderStatus::Pending->value)
                                    ->prefixIcon(Heroicon::Signal),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Entrega y forma de pago')
                    ->description('Solo aplica a pedidos de compañía aliada: tipo de entrega y cómo pagará.')
                    ->icon(Heroicon::MapPin)
                    ->extraAttributes([
                        'class' => 'fi-order-form-partner-delivery-section',
                    ])
                    ->visible(fn (Get $get): bool => self::partnerDeliverySectionVisible($get))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('partner_fulfillment_type')
                                    ->label('Tipo de entrega')
                                    ->options(OrderFulfillmentType::options())
                                    ->native(false)
                                    ->required(fn (Get $get): bool => self::partnerDeliverySectionVisible($get))
                                    ->default(OrderFulfillmentType::Delivery->value)
                                    ->dehydrated(fn (Get $get): bool => self::partnerDeliverySectionVisible($get))
                                    ->helperText('Delivery: envío a su ubicación. PickUp: retira en punto acordado.')
                                    ->prefixIcon(Heroicon::Truck),
                                Select::make('partner_payment_terms')
                                    ->label('Forma de pago')
                                    ->options(OrderPartnerPaymentTerms::options())
                                    ->native(false)
                                    ->live()
                                    ->required(fn (Get $get): bool => self::partnerDeliverySectionVisible($get))
                                    ->default(OrderPartnerPaymentTerms::Cash->value)
                                    ->dehydrated(fn (Get $get): bool => self::partnerDeliverySectionVisible($get))
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if ((string) $state !== OrderPartnerPaymentTerms::Cash->value) {
                                            $set('partner_cash_payment_method', null);
                                            self::resetPartnerPaymentReferenceFields($set);
                                            $set('partner_cash_payment_proof_path', null);
                                        }
                                    })
                                    ->helperText('De contado: elija el medio abajo. Crédito: sin selección de medio.')
                                    ->prefixIcon(Heroicon::Banknotes),
                                Select::make('partner_cash_payment_method')
                                    ->label('Opción de pago (de contado)')
                                    ->options(OrderPartnerCashPaymentMethod::options())
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        self::resetPartnerPaymentReferenceFields($set);
                                    })
                                    ->visible(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                        && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value)
                                    ->required(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                        && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value)
                                    ->dehydrated(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                        && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value)
                                    ->prefixIcon(Heroicon::DevicePhoneMobile)
                                    ->columnSpanFull(),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->extraAttributes([
                                'class' => 'fi-order-form-partner-payment-qr-grid',
                            ])
                            ->visible(fn (Get $get): bool => self::partnerCashQrAndRefsVisible($get))
                            ->schema([
                                Grid::make(1)
                                    ->extraAttributes([
                                        'class' => 'fi-order-form-partner-payment-qr-column',
                                    ])
                                    ->schema([
                                        Placeholder::make('partner_qr_pago_movil')
                                            ->label('Código QR — Pago móvil')
                                            ->content(new HtmlString(
                                                '<div class="flex justify-center py-1"><img src="'.e(asset(config('orders.partner_payment_qr.pago_movil'))).'" alt="QR Pago móvil" class="max-h-80 w-auto max-w-full rounded-lg border border-gray-200 shadow-sm dark:border-gray-600 sm:max-h-96" loading="lazy" /></div>'
                                            ))
                                            ->visible(fn (Get $get): bool => (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::PagoMovil->value),
                                        Placeholder::make('partner_qr_zelle')
                                            ->label('Código QR — Zelle')
                                            ->content(new HtmlString(
                                                '<div class="flex justify-center py-1"><img src="'.e(asset(config('orders.partner_payment_qr.zelle'))).'" alt="QR Zelle" class="max-h-80 w-auto max-w-full rounded-lg border border-gray-200 shadow-sm dark:border-gray-600 sm:max-h-96" loading="lazy" /></div>'
                                            ))
                                            ->visible(fn (Get $get): bool => (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::Zelle->value),
                                    ]),
                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('partner_pago_movil_reference')
                                            ->label('Referencia del pago móvil')
                                            ->placeholder('Ej. últimos dígitos o código de referencia')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::PagoMovil->value)
                                            ->required(fn (Get $get): bool => self::partnerCashQrAndRefsVisible($get)
                                                && (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::PagoMovil->value)
                                            ->dehydrated(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                                && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value
                                                && (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::PagoMovil->value)
                                            ->prefixIcon(Heroicon::Hashtag)
                                            ->columnSpanFull(),
                                        TextInput::make('partner_zelle_reference_name')
                                            ->label('Nombre Completo (Zelle)')
                                            ->placeholder('Nombre completo')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::Zelle->value)
                                            ->required(fn (Get $get): bool => self::partnerCashQrAndRefsVisible($get)
                                                && (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::Zelle->value)
                                            ->dehydrated(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                                && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value
                                                && (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::Zelle->value)
                                            ->prefixIcon(Heroicon::Envelope)
                                            ->columnSpanFull(),
                                        TextInput::make('partner_zelle_transaction_number')
                                            ->label('Número o referencia de transacción (Zelle)')
                                            ->placeholder('ID o referencia que envió su banco')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::Zelle->value)
                                            ->dehydrated(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                                && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value
                                                && (string) $get('partner_cash_payment_method') === OrderPartnerCashPaymentMethod::Zelle->value)
                                            ->prefixIcon(Heroicon::Identification)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        FileUpload::make('partner_cash_payment_proof_path')
                            ->label('Comprobante de la operación')
                            ->helperText('Adjunte imagen (JPG, PNG, WebP) o PDF del comprobante. Máximo 5 MB.')
                            ->disk('public')
                            ->directory('orders/partner-payment-proofs')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'image/gif',
                                'application/pdf',
                            ])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value)
                            ->required(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value)
                            ->dehydrated(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)
                                && (string) $get('partner_payment_terms') === OrderPartnerPaymentTerms::Cash->value),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Productos del pedido')
                    ->description('Líneas del pedido y resumen monetario. Los importes se calculan desde el catálogo (precio lista, descuento % e IVA si el producto grava IVA).')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        Checkbox::make('is_wholesale')
                            ->label('Pedido al mayor (cantidades por cajas)')
                            ->helperText(fn (Get $get): string => filter_var($get('is_wholesale'), FILTER_VALIDATE_BOOLEAN)
                                ? 'Modo mayorista: en cada línea indique cuántas cajas solicita de ese producto.'
                                : 'Modo al detalle: en cada línea indique la cantidad en unidades.')
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                self::applyComputedOrderTotals($set, $get);
                            })
                            ->default(false)
                            ->columnSpanFull(),
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->saveRelationshipsWhenHidden(false)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                self::applyComputedOrderTotals($set, $get);
                            })
                            ->addActionLabel('Añadir producto')
                            ->table([
                                TableColumn::make('Producto')->width('65%'),
                                TableColumn::make('Cantidad'),
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto')
                                    ->relationship(
                                        name: 'product',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'),
                                    )
                                    ->searchable(['name', 'active_ingredient'])
                                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => self::formatProductOptionLabelForOrderSelect($record))
                                    ->native(false)
                                    ->live()
                                    ->required()
                                    ->helperText('Busque por nombre comercial o por principio activo.')
                                    ->prefixIcon(Heroicon::Cube),
                                TextInput::make('quantity')
                                    ->label(fn (Get $get): string => self::orderItemQuantityLabel($get))
                                    ->helperText(fn (Get $get): string => self::orderItemQuantityHelper($get))
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->live(onBlur: true)
                                    ->required()
                                    ->default(1)
                                    ->prefixIcon(Heroicon::Calculator),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::enrichOrderItemData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Model $record): array => self::enrichOrderItemData($data))
                            ->columnSpanFull(),
                        Fieldset::make('Totales del pedido')
                            ->columnSpanFull()
                            ->columns(1)
                            ->extraAttributes([
                                'class' => 'fi-order-form-line-totals',
                            ])
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                    'xl' => 4,
                                ])
                                    ->extraAttributes([
                                        'class' => 'fi-order-form-line-totals-grid',
                                    ])
                                    ->schema([
                                        TextInput::make('subtotal')
                                            ->label('Subtotal')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->numeric()
                                            ->default(0.0)
                                            ->prefix('$')
                                            ->helperText('Base imponible (tras descuento % del catálogo).'),
                                        TextInput::make('discount_total')
                                            ->label('Descuentos')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->numeric()
                                            ->default(0.0)
                                            ->prefix('$')
                                            ->helperText('Descuento comercial por línea.'),
                                        TextInput::make('tax_total')
                                            ->label('IVA')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->numeric()
                                            ->default(0.0)
                                            ->prefix('$')
                                            ->helperText('Solo productos con «Grava IVA».'),
                                        TextInput::make('total')
                                            ->label('Total')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->numeric()
                                            ->default(0.0)
                                            ->prefix('$')
                                            ->helperText('Suma de líneas (base + IVA).')
                                            ->extraFieldWrapperAttributes([
                                                'class' => 'fi-order-form-total-field',
                                            ])
                                            ->extraInputAttributes([
                                                'class' => 'tabular-nums',
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Entrega')
                    ->description('Contacto, dirección y seguimiento logístico del envío.')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('delivery_recipient_name')
                                    ->label('Quien recibe')
                                    ->placeholder('Nombre completo')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::UserCircle),
                                TextInput::make('delivery_phone')
                                    ->label('Teléfono de contacto')
                                    ->tel()
                                    ->placeholder('Ej. 300 123 4567')
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::Phone),
                            ]),
                        TextInput::make('delivery_recipient_document')
                            ->label('Cédula o RIF')
                            ->helperText('Documento de identidad o RIF de quien recibe el envío.')
                            ->placeholder('Ej. V-12345678 o J-12345678-9')
                            ->maxLength(64)
                            ->prefixIcon(Heroicon::Identification)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::partnerDeliverySectionVisible($get))
                            ->dehydrated(fn (Get $get): bool => self::partnerDeliverySectionVisible($get)),
                        TextInput::make('delivery_address')
                            ->label('Dirección de entrega')
                            ->placeholder('Calle, número, barrio, torre/apto')
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Home)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('delivery_city')
                                    ->label('Ciudad')
                                    ->maxLength(100)
                                    ->prefixIcon(Heroicon::MapPin),
                                TextInput::make('delivery_state')
                                    ->label('Departamento / estado')
                                    ->maxLength(100)
                                    ->prefixIcon(Heroicon::Map),
                            ]),
                        Textarea::make('delivery_notes')
                            ->label('Indicaciones para entrega')
                            ->placeholder('Horario, portería, punto de referencia…')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Observaciones solo para el equipo (no suelen mostrarse al cliente).')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Incidencias, acuerdos comerciales, llamadas…')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function enrichOrderItemData(array $data): array
    {
        $product = Product::query()->find($data['product_id'] ?? null);
        if ($product === null) {
            return $data;
        }

        $quantity = max(0.001, (float) ($data['quantity'] ?? 1));
        $line = OrderTotalsCalculator::lineAmounts($product, $quantity);

        return array_merge($data, $line, [
            'inventory_id' => null,
        ]);
    }

    /**
     * Etiqueta en el selector de línea de pedido: nombre + principios activos (texto del JSON / array).
     */
    private static function formatProductOptionLabelForOrderSelect(Product $record): string
    {
        $name = (string) $record->name;
        $ingredients = $record->active_ingredient;
        if (! is_array($ingredients) || $ingredients === []) {
            return $name;
        }

        $values = array_values(array_filter(array_map('strval', $ingredients), fn (string $v): bool => $v !== ''));
        if ($values === []) {
            return $name;
        }

        $joined = implode(', ', array_slice($values, 0, 4));
        if (mb_strlen($joined) > 72) {
            $joined = mb_substr($joined, 0, 69).'…';
        }

        return $name.' · '.$joined;
    }

    private static function applyComputedOrderTotals(Set $set, Get $get): void
    {
        $items = $get('items');
        if (! is_array($items)) {
            $items = [];
        }

        $agg = OrderTotalsCalculator::aggregateFromItemStates($items);
        $set('subtotal', $agg['subtotal']);
        $set('tax_total', $agg['tax_total']);
        $set('discount_total', $agg['discount_total']);
        $set('total', $agg['total']);
    }

    private static function orderItemQuantityLabel(Get $get): string
    {
        $wholesale = filter_var($get('../../is_wholesale'), FILTER_VALIDATE_BOOLEAN);

        return $wholesale ? 'Cantidad (cajas)' : 'Cantidad (unidades)';
    }

    private static function orderItemQuantityHelper(Get $get): string
    {
        $wholesale = filter_var($get('../../is_wholesale'), FILTER_VALIDATE_BOOLEAN);

        return $wholesale
            ? 'La cantidad en esta línea es número de cajas del producto (pedido al mayor).'
            : 'La cantidad en esta línea es en unidades al detalle.';
    }

    private static function orderForPartnerFromGet(Get $get): bool
    {
        return filter_var($get('order_for_partner'), FILTER_VALIDATE_BOOLEAN);
    }

    private static function clientSelectHidden(Get $get): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return true;
        }
        if ($user->isPartnerCompanyUser()) {
            return true;
        }
        if ($user->isAdministrator()) {
            return self::orderForPartnerFromGet($get);
        }

        return false;
    }

    private static function clientSelectRequired(Get $get): bool
    {
        return ! self::clientSelectHidden($get);
    }

    private static function clientSelectDehydrated(Get $get): bool
    {
        return ! self::clientSelectHidden($get);
    }

    private static function partnerSelectHidden(Get $get): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return true;
        }
        if ($user->isPartnerCompanyUser()) {
            return true;
        }
        if ($user->isAdministrator()) {
            return ! self::orderForPartnerFromGet($get);
        }

        return true;
    }

    private static function partnerSelectRequired(Get $get): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }
        if ($user->isPartnerCompanyUser()) {
            return true;
        }
        if ($user->isAdministrator()) {
            return self::orderForPartnerFromGet($get);
        }

        return false;
    }

    private static function partnerSelectDehydrated(Get $get): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }
        if ($user->isPartnerCompanyUser()) {
            return true;
        }
        if ($user->isAdministrator()) {
            return self::orderForPartnerFromGet($get);
        }

        return false;
    }

    private static function partnerDeliverySectionVisible(Get $get): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isPartnerCompanyUser()) {
            return true;
        }

        return $user->isAdministrator()
            && self::orderForPartnerFromGet($get)
            && filled($get('partner_company_id'));
    }

    private static function partnerCashQrAndRefsVisible(Get $get): bool
    {
        if (! self::partnerDeliverySectionVisible($get)) {
            return false;
        }

        if ((string) $get('partner_payment_terms') !== OrderPartnerPaymentTerms::Cash->value) {
            return false;
        }

        return in_array((string) $get('partner_cash_payment_method'), [
            OrderPartnerCashPaymentMethod::PagoMovil->value,
            OrderPartnerCashPaymentMethod::Zelle->value,
        ], true);
    }

    private static function resetPartnerPaymentReferenceFields(Set $set): void
    {
        $set('partner_pago_movil_reference', null);
        $set('partner_zelle_reference_name', null);
        $set('partner_zelle_transaction_number', null);
    }

    private static function resetPartnerDeliveryFields(Set $set): void
    {
        $set('partner_fulfillment_type', null);
        $set('partner_payment_terms', null);
        $set('partner_cash_payment_method', null);
        self::resetPartnerPaymentReferenceFields($set);
        $set('partner_cash_payment_proof_path', null);
    }
}
