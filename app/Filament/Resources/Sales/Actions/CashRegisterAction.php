<?php

namespace App\Filament\Resources\Sales\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Dolar\DolarApiDolaresService;
use App\Services\Dolar\DolarApiEstadoService;
use App\Support\Finance\DefaultIgtfRate;
use App\Support\Finance\DefaultVatRate;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component as LivewireComponent;
use RuntimeException;

final class CashRegisterAction
{
    /**
     * Primer paso: buscar cliente (nombre o documento). Al elegir uno se abre la caja; «Continuar» sin elegir = mostrador.
     */
    public static function makeClientGate(): Action
    {
        return Action::make('posClientGate')
            ->label('Caja')
            ->icon(Heroicon::Cube)
            ->color('primary')
            ->modalHeading('Cliente de la venta')
            ->modalDescription('Busque por nombre o documento; al elegir un cliente se abre el carrito. Si no aparece en la lista, complete abajo nombre, cédula y teléfono y pulse Continuar o Enter para registrarlo y pasar a los productos. «Continuar» con todo vacío = mostrador.')
            ->modalIcon(Heroicon::User)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Continuar')
            ->modalCancelAction(fn (Action $action): Action => $action->color('gray'))
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
            ])
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $schema?->fill([
                    'client_id' => null,
                    'quick_client_name' => null,
                    'quick_client_document' => null,
                    'quick_client_phone' => null,
                ]);

                $livewire = $action->getLivewire();
                if ($livewire instanceof LivewireComponent) {
                    $livewire->js(self::mountPosClientGateFocusSelectJs());
                }
            })
            ->schema([
                Grid::make(1)
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->placeholder('Nombre o documento de identidad…')
                            ->extraAttributes([
                                'class' => 'farmadoc-pos-client-gate-select',
                            ])
                            ->live()
                            ->searchable()
                            ->searchDebounce(100)
                            ->getSearchResultsUsing(fn (string $search): array => self::posClientSearchResults($search))
                            ->getOptionLabelUsing(fn ($value): ?string => self::posClientOptionLabel($value))
                            ->afterStateUpdated(function (mixed $state, Select $component): void {
                                if (blank($state)) {
                                    return;
                                }

                                $livewire = $component->getLivewire();
                                if (! is_object($livewire) || ! method_exists($livewire, 'replaceMountedAction')) {
                                    return;
                                }

                                $livewire->replaceMountedAction('posRegister', [
                                    'client_id' => (int) $state,
                                ]);
                            })
                            ->native(false)
                            ->prefixIcon(Heroicon::User)
                            ->columnSpanFull(),
                        Section::make('Cliente nuevo')
                            ->description('Visible mientras no haya cliente seleccionado arriba. Registra en el catálogo y abre la caja.')
                            ->icon(Heroicon::UserPlus)
                            ->iconColor('gray')
                            ->visible(fn (Get $get): bool => blank($get('client_id')))
                            ->extraAttributes([
                                'class' => 'farmadoc-pos-client-quick-register',
                            ])
                            ->schema([
                                TextInput::make('quick_client_name')
                                    ->label('Nombre completo')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('quick_client_document')
                                    ->label('Cédula / documento')
                                    ->maxLength(120)
                                    ->columnSpan(['default' => 1, 'sm' => 1]),
                                TextInput::make('quick_client_phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(120)
                                    ->columnSpan(['default' => 1, 'sm' => 1]),
                            ])
                            ->columns(['default' => 1, 'sm' => 2])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Action $action): void {
                $livewire = $action->getLivewire();
                if (! is_object($livewire) || ! method_exists($livewire, 'replaceMountedAction')) {
                    return;
                }

                $cid = $data['client_id'] ?? null;
                if (filled($cid)) {
                    $livewire->replaceMountedAction('posRegister', [
                        'client_id' => (int) $cid,
                    ]);

                    return;
                }

                $quickName = trim((string) ($data['quick_client_name'] ?? ''));
                $quickDoc = trim((string) ($data['quick_client_document'] ?? ''));
                $quickPhone = trim((string) ($data['quick_client_phone'] ?? ''));

                $anyQuick = $quickName !== '' || $quickDoc !== '' || $quickPhone !== '';
                if ($anyQuick) {
                    if ($quickName === '' || $quickDoc === '' || $quickPhone === '') {
                        Notification::make()
                            ->title('Datos incompletos')
                            ->body('Para registrar un cliente nuevo complete los tres campos (nombre, cédula y teléfono), o déjelos vacíos y use «Continuar» para mostrador.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $existing = Client::query()
                        ->where('document_number', $quickDoc)
                        ->first();
                    if ($existing instanceof Client) {
                        Notification::make()
                            ->title('Cliente ya registrado')
                            ->body('Ya existe un cliente con ese documento; se abrirá la venta con ese registro.')
                            ->success()
                            ->send();
                        $livewire->replaceMountedAction('posRegister', [
                            'client_id' => $existing->id,
                        ]);

                        return;
                    }

                    $client = self::createClientFromPosQuickForm($quickName, $quickDoc, $quickPhone);
                    Notification::make()
                        ->title('Cliente registrado')
                        ->body('Se guardó el cliente y puede cargar productos.')
                        ->success()
                        ->send();
                    $livewire->replaceMountedAction('posRegister', [
                        'client_id' => $client->id,
                    ]);

                    return;
                }

                $livewire->replaceMountedAction('posRegister', [
                    'client_id' => null,
                ]);
            });
    }

    /**
     * Caja registradora (carrito, cobro). Se abre desde {@see makeClientGate()} con el cliente en argumentos.
     */
    public static function makeRegister(): Action
    {
        return Action::make('posRegister')
            ->label('Caja registradora')
            ->modalHeading('Caja registradora')
            ->modalDescription('Cargue productos, revise totales y confirme el cobro.')
            ->modalIcon(Heroicon::Banknotes)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Registrar venta')
            ->modalCancelAction(fn (Action $action): Action => $action->color('danger'))
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
            ])
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $args = $action->getArguments();
                $clientId = $args['client_id'] ?? null;

                $state = self::defaultPosFormState();
                if (filled($clientId)) {
                    $state['client_id'] = (int) $clientId;
                }

                $schema?->fill($state);

                $livewire = $action->getLivewire();
                if ($livewire instanceof LivewireComponent) {
                    $livewire->js(self::mountPosBarcodeAutoAdvanceJs());
                    $livewire->js(self::focusPosLineProductSearchJs(pickFirstItem: true));
                }
            })
            ->schema([
                Grid::make([
                    'default' => 1,
                    'lg' => 12,
                ])
                    ->extraAttributes([
                        'class' => 'farmadoc-pos-main-layout',
                    ])
                    ->schema([
                        Section::make('Venta')
                            ->description('Cliente definido en el paso anterior. Busque productos, indique cantidades y confirme el cobro.')
                            ->icon(Heroicon::ShoppingCart)
                            ->iconColor('primary')
                            ->extraAttributes([
                                'class' => 'farmadoc-pos-cart-section',
                            ])
                            ->schema([
                                Hidden::make('client_id'),
                                TextEntry::make('pos_client_summary')
                                    ->label('Cliente')
                                    ->state(function (Get $get): string {
                                        $id = $get('client_id');
                                        if (! filled($id)) {
                                            return 'Mostrador / sin cliente';
                                        }

                                        $client = Client::query()
                                            ->select(['id', 'name', 'document_number'])
                                            ->find((int) $id);

                                        if (! $client) {
                                            return 'Cliente #'.(int) $id;
                                        }

                                        $doc = filled($client->document_number)
                                            ? ' · Doc. '.$client->document_number
                                            : '';

                                        return $client->name.$doc;
                                    })
                                    ->dehydrated(false)
                                    ->icon(Heroicon::User)
                                    ->iconColor('gray')
                                    ->columnSpanFull(),
                                Repeater::make('line_items')
                                    ->label('')
                                    ->reorderable(false)
                                    ->addActionLabel('Añadir producto')
                                    ->defaultItems(1)
                                    ->minItems(1)
                                    ->live()
                                    ->partiallyRenderAfterActionsCalled(false)
                                    ->itemLabel(function (array $state, Get $get): string|Htmlable {
                                        if (! filled($state['product_id'] ?? null)) {
                                            return 'Nueva línea';
                                        }

                                        $product = self::posProduct((int) $state['product_id']);
                                        $title = $product
                                            ? (filled($product->barcode)
                                                ? $product->barcode.' · '.$product->name
                                                : $product->name)
                                            : 'Producto';
                                        $total = self::formatMoney(self::computeLineTotalFromRowState($state, $get));

                                        return new HtmlString(
                                            e($title).' · <span class="farmadoc-pos-repeater-item-total">'.$total.'</span>'
                                        );
                                    })
                                    ->extraAttributes([
                                        'class' => 'farmadoc-pos-line-items-repeater fi-fixed-positioning-context',
                                    ])
                                    ->table([
                                        TableColumn::make('Producto')
                                            ->width('70%'),
                                        TableColumn::make('Cantidad'),
                                    ])
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Producto')
                                            ->searchable()
                                            ->searchDebounce(200)
                                            ->disabled(fn (): bool => blank(Auth::user()?->branch_id))
                                            ->getSearchResultsUsing(function (string $search): array {
                                                $branchId = Auth::user()?->branch_id;
                                                if (blank($branchId)) {
                                                    return [];
                                                }

                                                return self::searchInventoryProductsForBranch((int) $branchId, $search);
                                            })
                                            ->getOptionLabelUsing(function ($value): ?string {
                                                if (blank($value)) {
                                                    return null;
                                                }

                                                $branchId = Auth::user()?->branch_id;
                                                if (blank($branchId)) {
                                                    return null;
                                                }

                                                $id = (int) $value;
                                                self::warmPosDataForBranch((int) $branchId, [$id]);
                                                $product = self::posProduct($id);
                                                if (! $product) {
                                                    return null;
                                                }

                                                $label = filled($product->barcode)
                                                    ? $product->barcode.' · '.$product->name
                                                    : $product->name;
                                                $inv = self::posBranchInventory((int) $branchId, $id);
                                                if ($inv instanceof Inventory) {
                                                    $label .= ' · '.self::formatMoney((float) ($product->sale_price ?? 0));
                                                }

                                                return $label;
                                            })
                                            ->afterStateUpdated(function (
                                                mixed $state,
                                                mixed $old,
                                                Set $set,
                                                Get $get,
                                                $livewire,
                                                Select $component,
                                            ): void {
                                                if (blank($state) || filled($old)) {
                                                    return;
                                                }

                                                $fieldPath = $component->getStatePath();
                                                if (! is_string($fieldPath) || ! Str::endsWith($fieldPath, '.product_id')) {
                                                    return;
                                                }

                                                /*
                                                         * El Select está en el ítem del repeater; un solo «..» devuelve la fila
                                                         * {product_id, quantity}, no todo line_items. Usamos la ruta absoluta.
                                                         */
                                                $itemContainerPath = Str::beforeLast($fieldPath, '.');
                                                $lineItemsPath = Str::beforeLast($itemContainerPath, '.');
                                                $currentItemKey = Str::afterLast($itemContainerPath, '.');

                                                $lineItems = $get($lineItemsPath, isAbsolute: true);
                                                if (! is_array($lineItems) || $lineItems === []) {
                                                    return;
                                                }

                                                $keys = array_keys($lineItems);
                                                $lastKey = $keys[array_key_last($keys)];

                                                if ((string) $currentItemKey !== (string) $lastKey) {
                                                    return;
                                                }

                                                $keysList = array_values($keys);
                                                $newProductId = (int) $state;

                                                $mergeTargetKey = null;
                                                foreach ($keysList as $k) {
                                                    if ((string) $k === (string) $currentItemKey) {
                                                        continue;
                                                    }

                                                    $row = $lineItems[$k] ?? null;
                                                    if (is_array($row) && filled($row['product_id'] ?? null)
                                                        && (int) $row['product_id'] === $newProductId) {
                                                        $mergeTargetKey = $k;
                                                        break;
                                                    }
                                                }

                                                if ($mergeTargetKey !== null) {
                                                    $targetRow = $lineItems[$mergeTargetKey] ?? [];
                                                    $targetQty = is_array($targetRow)
                                                        ? (float) ($targetRow['quantity'] ?? 1)
                                                        : 1.0;
                                                    $currRow = $lineItems[$currentItemKey] ?? [];
                                                    $currQty = is_array($currRow)
                                                        ? (float) ($currRow['quantity'] ?? 1)
                                                        : 1.0;
                                                    $mergedQty = round(
                                                        max(0.001, $targetQty + max(0.001, $currQty)),
                                                        3,
                                                    );

                                                    $set("{$lineItemsPath}.{$mergeTargetKey}.quantity", $mergedQty, isAbsolute: true);
                                                    $set("{$lineItemsPath}.{$currentItemKey}.product_id", null, isAbsolute: true);
                                                    $set("{$lineItemsPath}.{$currentItemKey}.quantity", 1, isAbsolute: true);

                                                    if ($livewire instanceof LivewireComponent) {
                                                        $livewire->js(self::focusPosLineProductSearchJs(pickFirstItem: false));
                                                    }

                                                    return;
                                                }

                                                /*
                                                 * Solo añadir una fila nueva con Set puntual (data_set). No reemplazar
                                                 * todo line_items: evita estado desincronizado y remount del Select
                                                 * que borra la búsqueda / valor en la primera interacción.
                                                 */
                                                $newKey = (string) Str::uuid();
                                                $set("{$lineItemsPath}.{$newKey}", [
                                                    'product_id' => null,
                                                    'quantity' => 1,
                                                ], isAbsolute: true);

                                                if (! $livewire instanceof LivewireComponent) {
                                                    return;
                                                }

                                                $livewire->js(self::focusPosLineProductSearchJs(pickFirstItem: false));
                                            })
                                            ->live()
                                            ->native(false),
                                        TextInput::make('quantity')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->minValue(0.001)
                                            ->step(0.001)
                                            ->default(1)
                                            ->required()
                                            ->live(debounce: 150)
                                            ->inlinePrefix()
                                            ->inlineSuffix()
                                            ->prefixAction(
                                                Action::make('decreaseQuantity')
                                                    ->label(__('Menos'))
                                                    ->icon(Heroicon::Minus)
                                                    ->color('gray')
                                                    ->size(Size::Small)
                                                    ->action(function (Set $set, Get $get): void {
                                                        $current = (float) ($get('quantity') ?? 0);
                                                        $next = max(0.001, round($current - 1, 3));
                                                        $set('quantity', $next);
                                                    }),
                                                isInline: true,
                                            )
                                            ->suffixAction(
                                                Action::make('increaseQuantity')
                                                    ->label(__('Más'))
                                                    ->icon(Heroicon::Plus)
                                                    ->color('gray')
                                                    ->size(Size::Small)
                                                    ->action(function (Set $set, Get $get): void {
                                                        $current = (float) ($get('quantity') ?? 0);
                                                        $next = round(max(0.001, $current) + 1, 3);
                                                        $set('quantity', $next);
                                                    }),
                                                isInline: true,
                                            )
                                            ->extraAttributes([
                                                'class' => 'farmadoc-pos-qty-field',
                                            ]),
                                    ]),
                            ])
                            ->columns(1)
                            ->columnSpan(['lg' => 8])
                            ->extraAttributes([
                                'class' => 'farmadoc-pos-meta-section farmadoc-pos-cart-section',
                            ]),

                        Grid::make(1)
                            ->extraAttributes([
                                'class' => 'farmadoc-pos-summary-stack',
                            ])
                            ->schema([
                                Section::make('Total a cobrar')
                                    // ->description('IVA solo en productos con «Grava IVA». IGTF (configurable) solo si el cobro es Efectivo USD. Descuentos globales no aplican en pagos solo en dólares (transferencia/Zelle).')
                                    ->icon(Heroicon::Banknotes)
                                    ->iconColor('primary')
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => 'farmadoc-pos-total-ios-card',
                                            ])
                                            ->schema([
                                                TextEntry::make('pos_total_banner')
                                                    ->hiddenLabel()
                                                    ->alignment(Alignment::Center)
                                                    ->weight(FontWeight::Bold)
                                                    ->size(TextSize::Medium)
                                                    ->state(fn (Get $get): string => self::formatMoney(self::computeSaleTotal($get)))
                                                    ->dehydrated(false)
                                                    ->extraEntryWrapperAttributes([
                                                        'class' => 'farmadoc-pos-total-ios__amount',
                                                    ]),
                                                TextEntry::make('pos_totals_breakdown')
                                                    ->hiddenLabel()
                                                    ->alignment(Alignment::Center)
                                                    ->html()
                                                    ->state(fn (Get $get): HtmlString => self::buildPosTotalsBreakdownHtml($get))
                                                    ->dehydrated(false)
                                                    ->extraEntryWrapperAttributes([
                                                        'class' => 'farmadoc-pos-total-ios__breakdown text-xs text-gray-600 dark:text-gray-400',
                                                    ]),
                                                TextEntry::make('pos_total_banner_ves')
                                                    ->hiddenLabel()
                                                    ->alignment(Alignment::Center)
                                                    ->html()
                                                    ->state(fn (Get $get): HtmlString => self::buildTotalVesBannerHtml($get))
                                                    ->dehydrated(false)
                                                    ->extraEntryWrapperAttributes([
                                                        'class' => 'farmadoc-pos-total-ios__sub',
                                                    ]),
                                                TextInput::make('ves_usd_rate')
                                                    ->hiddenLabel()
                                                    ->type('hidden')
                                                    ->dehydrated()
                                                    ->default(null),
                                                TextInput::make('ves_usd_rate_manual')
                                                    ->label('Tasa Bs. por 1 USD (manual)')
                                                    ->helperText('Solo si la API no está disponible. Se usa para convertir USD a bolívares.')
                                                    ->numeric()
                                                    ->minValue(0.0001)
                                                    ->step(0.01)
                                                    ->prefix('Bs.')
                                                    ->suffix('× 1 USD')
                                                    ->live(debounce: 300)
                                                    ->visible(fn (Get $get): bool => ! self::hasValidApiRate($get)),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->extraAttributes([
                                        'class' => 'farmadoc-pos-total-section farmadoc-pos-total-section--ios',
                                    ]),
                                Section::make('Formas de pago')
                                    // ->description('Seleccione la forma de pago y el monto a pagar.')
                                    ->icon(Heroicon::CreditCard)
                                    ->iconColor('primary')
                                    ->extraAttributes([
                                        'class' => 'farmadoc-pos-payment-section',
                                    ])
                                    ->schema([
                                        Select::make('payment_method')
                                            ->label('Cobro')
                                            ->options([
                                                'efectivo_usd' => 'Efectivo USD',
                                                'efectivo_ves' => 'Efectivo VES',
                                                'punto_venta_ves' => 'Punto de Venta',
                                                'transfer_ves' => 'Transferencia VES',
                                                'zelle' => 'Zelle',
                                                'pago_movil' => 'Pago Movil',
                                                'mixed' => 'Pago Multiple',
                                            ])
                                            ->default('punto_venta_ves')
                                            ->required()
                                            ->live()
                                            ->native(false)
                                            ->prefixIcon(Heroicon::CreditCard),
                                        TextInput::make('card_last4')
                                            ->label('Últimos 4 dígitos de la tarjeta')
                                            ->helperText('Obligatorio para Punto de Venta: exactamente 4 dígitos. Los errores se muestran debajo del campo.')
                                            ->inputMode('numeric')
                                            // Sin ->required(): evita el atributo HTML `required` y el tooltip nativo del navegador (poco contraste).
                                            ->markAsRequired(fn (Get $get): bool => $get('payment_method') === 'punto_venta_ves')
                                            ->requiredIf('payment_method', 'punto_venta_ves')
                                            ->regex('/^\d{4}$/')
                                            ->validationMessages([
                                                'required_if' => 'Debe indicar los últimos 4 dígitos de la tarjeta para Punto de Venta.',
                                                'regex' => 'Ingrese exactamente 4 dígitos numéricos.',
                                            ])
                                            ->visible(fn (Get $get): bool => $get('payment_method') === 'punto_venta_ves'),
                                        TextInput::make('mixed_usd_paid')
                                            ->label('Pago en USD (usuario)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->prefix('$')
                                            ->live(debounce: 300)
                                            ->visible(fn (Get $get): bool => $get('payment_method') === 'mixed'),
                                        TextEntry::make('payment_usd_preview')
                                            ->label('payment_usd')
                                            ->state(fn (Get $get): string => self::formatMoney(self::computePaymentBreakdownForForm($get)['payment_usd']))
                                            ->dehydrated(false),
                                        TextEntry::make('payment_ves_preview')
                                            ->label('payment_ves')
                                            ->state(fn (Get $get): string => self::formatBolivaresReferenceFromVes(self::computePaymentBreakdownForForm($get)['payment_ves']))
                                            ->dehydrated(false),
                                        TextInput::make('reference')
                                            ->label('Referencia de pago')
                                            ->helperText('Obligatoria para pagos en bolivares y para Zelle.')
                                            ->maxLength(255)
                                            ->markAsRequired(function (Get $get): bool {
                                                $method = (string) ($get('payment_method') ?? '');

                                                if (in_array($method, ['transfer_ves', 'pago_movil', 'zelle'], true)) {
                                                    return true;
                                                }

                                                if ($method === 'mixed') {
                                                    return self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001;
                                                }

                                                return false;
                                            })
                                            ->rules(fn (Get $get): array => [
                                                Rule::requiredIf(function () use ($get): bool {
                                                    $method = (string) ($get('payment_method') ?? '');

                                                    if (in_array($method, ['transfer_ves', 'pago_movil', 'zelle'], true)) {
                                                        return true;
                                                    }

                                                    if ($method === 'mixed') {
                                                        return self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001;
                                                    }

                                                    return false;
                                                }),
                                            ])
                                            ->validationMessages([
                                                'required' => 'Debe indicar una referencia de pago para pagos en bolivares y para Zelle.',
                                            ])
                                            ->visible(fn (Get $get): bool => in_array($get('payment_method'), ['transfer_ves', 'pago_movil', 'zelle', 'mixed'], true)),
                                    ]),
                            ])
                            ->columnSpan(['lg' => 4]),
                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Action $action) {
                $branchId = Auth::user()?->branch_id;

                if (blank($branchId)) {
                    Notification::make()
                        ->title('Tu usuario no tiene sucursal asignada.')
                        ->danger()
                        ->send();

                    return;
                }

                $paymentMethod = (string) ($data['payment_method'] ?? '');
                $paymentReference = trim((string) ($data['reference'] ?? ''));
                $cardLast4 = trim((string) ($data['card_last4'] ?? ''));

                $lines = collect($data['line_items'] ?? [])
                    ->filter(function (mixed $row): bool {
                        if (! is_array($row)) {
                            return false;
                        }

                        return filled($row['product_id'] ?? null)
                            && (float) ($row['quantity'] ?? 0) > 0;
                    })
                    ->values()
                    ->all();

                if ($lines === []) {
                    Notification::make()
                        ->title('Debe cargar al menos un producto')
                        ->body('Seleccione un producto y una cantidad mayor a cero en al menos una línea antes de registrar la venta.')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $productIds = collect($lines)->pluck('product_id')->unique()->values()->all();
                $products = Product::query()
                    ->select([
                        'id',
                        'name',
                        'barcode',
                        'sku',
                        'sale_price',
                        'cost_price',
                        'applies_vat',
                    ])
                    ->whereIn('id', $productIds)
                    ->get()
                    ->keyBy('id');

                $branchId = (int) $branchId;

                foreach ($productIds as $pid) {
                    self::ensurePosBranchInventoryRecord($branchId, (int) $pid);
                }

                $inventoryByProductId = Inventory::query()
                    ->where('branch_id', $branchId)
                    ->whereIn('product_id', $productIds)
                    ->get()
                    ->keyBy('product_id');

                $validLines = [];

                foreach ($lines as $row) {
                    $productId = (int) $row['product_id'];
                    $qty = (float) $row['quantity'];
                    $product = $products->get($productId);
                    if (! $product) {
                        continue;
                    }

                    $inventory = $inventoryByProductId->get($productId)
                        ?? self::ensurePosBranchInventoryRecord($branchId, $productId);
                    if (! $inventory) {
                        Notification::make()
                            ->title('No se pudo preparar el inventario')
                            ->body('Revise el carrito: '.$product->name.'.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $validLines[] = [
                        'product' => $product,
                        'quantity' => $qty,
                        'inventory' => $inventory,
                    ];
                }

                if ($validLines === []) {
                    Notification::make()
                        ->title('No se pudieron resolver los productos seleccionados.')
                        ->danger()
                        ->send();

                    return;
                }

                $discountRequested = (float) ($data['discount_total'] ?? 0);

                $pricing = self::finalizePosPricingFromValidLines(
                    $validLines,
                    $paymentMethod,
                    $discountRequested,
                );

                $subtotal = $pricing['subtotal'];
                $taxTotal = $pricing['tax_total'];
                $igtfTotal = $pricing['igtf_total'];
                $discountTotal = $pricing['discount_total'];
                $documentTotal = $pricing['document_total'];

                $payloadItems = [];

                foreach ($validLines as $i => $entry) {
                    $product = $entry['product'];
                    $qty = $entry['quantity'];
                    $inventory = $entry['inventory'];
                    $productId = (int) $product->id;
                    $unit = (float) ($product->sale_price ?? 0);
                    $unitCost = (float) ($product->cost_price ?? 0);
                    $pl = $pricing['per_line'][$i];
                    $lineSubtotal = $pl['line_subtotal'];
                    $taxAmount = $pl['tax_amount'];
                    $lineTotal = $pl['line_total'];
                    $lineCostTotal = round($qty * $unitCost, 2);
                    $grossProfit = round($lineTotal - $lineCostTotal, 2);

                    $payloadItems[] = [
                        'product_id' => $productId,
                        'inventory_id' => (int) $inventory->id,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'unit_cost' => $unitCost,
                        'discount_amount' => 0.0,
                        'line_subtotal' => $lineSubtotal,
                        'tax_amount' => $taxAmount,
                        'line_total' => $lineTotal,
                        'line_cost_total' => $lineCostTotal,
                        'gross_profit' => $grossProfit,
                        'product_name_snapshot' => $product->name,
                        'sku_snapshot' => $product->barcode,
                    ];
                }

                $vesUsdRate = self::effectiveVesUsdRateFromData($data);

                if ($documentTotal > 0
                    && self::requiresVesConversion($paymentMethod, $documentTotal, (float) ($data['mixed_usd_paid'] ?? 0))
                    && $vesUsdRate <= 0) {
                    Notification::make()
                        ->title('Indique la tasa Bs. por USD')
                        ->body('La API no devolvió una tasa válida. Ingrese manualmente el valor del bolívar para continuar.')
                        ->danger()
                        ->send();

                    return;
                }

                [$paymentUsd, $paymentVes] = self::resolvePaymentAmounts(
                    $documentTotal,
                    $paymentMethod,
                    mixedUsdPaid: (float) ($data['mixed_usd_paid'] ?? 0),
                    vesUsdRate: $vesUsdRate
                );

                if ($paymentMethod === 'punto_venta_ves' && ! preg_match('/^\d{4}$/', $cardLast4)) {
                    Notification::make()
                        ->title('Faltan los últimos 4 dígitos')
                        ->body('Para pagos por Punto de Venta debe indicar exactamente los últimos 4 dígitos de la tarjeta.')
                        ->danger()
                        ->send();
                    $action->halt();

                    return;
                }

                if ($paymentMethod === 'punto_venta_ves') {
                    $paymentReference = 'POS ****'.$cardLast4;
                }

                $shouldRequireReference = in_array($paymentMethod, ['transfer_ves', 'pago_movil', 'zelle'], true)
                    || ($paymentMethod === 'mixed' && $paymentVes > 0);

                if ($shouldRequireReference && $paymentReference === '') {
                    Notification::make()
                        ->title('Indique la referencia de pago')
                        ->body('La referencia es obligatoria para pagos en bolivares y para Zelle.')
                        ->danger()
                        ->send();

                    return;
                }

                $bcvVesPerUsd = ($vesUsdRate > 0.0 && $paymentVes > 0.00001)
                    ? round($vesUsdRate, 6)
                    : null;

                $actor = Auth::user()?->email
                    ?? Auth::user()?->name
                    ?? 'sistema';

                try {
                    $sale = DB::transaction(function () use ($branchId, $data, $payloadItems, $lines, $products, $subtotal, $taxTotal, $igtfTotal, $discountTotal, $documentTotal, $actor, $paymentMethod, $paymentUsd, $paymentVes, $paymentReference, $bcvVesPerUsd): Sale {
                        $qtyByProduct = [];
                        foreach ($lines as $row) {
                            $pid = (int) $row['product_id'];
                            $qtyByProduct[$pid] = ($qtyByProduct[$pid] ?? 0.0) + (float) $row['quantity'];
                        }
                        ksort($qtyByProduct);

                        foreach ($qtyByProduct as $productId => $totalQty) {
                            CashRegisterAction::ensurePosBranchInventoryRecord($branchId, (int) $productId);

                            $inventory = Inventory::query()
                                ->where('branch_id', $branchId)
                                ->where('product_id', $productId)
                                ->lockForUpdate()
                                ->first();

                            $product = $products->get($productId);
                            $productName = $product?->name ?? 'Producto';

                            if (! $inventory) {
                                throw new RuntimeException('No hay inventario para '.$productName.' en esta sucursal.');
                            }

                            $available = (float) $inventory->quantity - (float) $inventory->reserved_quantity;
                            if (! $inventory->allow_negative_stock && $available + 0.0001 < $totalQty) {
                                throw new RuntimeException(
                                    'Stock insuficiente para '.$productName.'. Disponible: '.number_format($available, 3, '.', '').'.'
                                );
                            }
                        }

                        $sale = Sale::query()->create([
                            'sale_number' => self::uniqueSaleNumber(),
                            'branch_id' => (int) $branchId,
                            'client_id' => filled($data['client_id'] ?? null) ? (int) $data['client_id'] : null,
                            'status' => SaleStatus::Completed,
                            'subtotal' => round($subtotal, 2),
                            'tax_total' => round($taxTotal, 2),
                            'igtf_total' => round($igtfTotal, 2),
                            'discount_total' => round($discountTotal, 2),
                            'total' => $documentTotal,
                            'payment_method' => $paymentMethod,
                            'payment_usd' => round($paymentUsd, 2),
                            'payment_ves' => round($paymentVes, 2),
                            'bcv_ves_per_usd' => $bcvVesPerUsd,
                            'reference' => $paymentReference !== '' ? $paymentReference : null,
                            'payment_status' => 'paid',
                            'notes' => null,
                            'sold_at' => now(),
                            'created_by' => $actor,
                            'updated_by' => $actor,
                        ]);

                        foreach ($payloadItems as $item) {
                            $sale->items()->create($item);
                        }

                        foreach ($lines as $row) {
                            $productId = (int) $row['product_id'];
                            $qty = (float) $row['quantity'];
                            $product = $products->get($productId);
                            if (! $product) {
                                continue;
                            }

                            CashRegisterAction::ensurePosBranchInventoryRecord($branchId, $productId);

                            $inventory = Inventory::query()
                                ->where('branch_id', $branchId)
                                ->where('product_id', $productId)
                                ->lockForUpdate()
                                ->first();

                            if (! $inventory) {
                                throw new RuntimeException('Inventario no encontrado para '.$product->name.'.');
                            }

                            $inventory->quantity = round((float) $inventory->quantity - $qty, 3);
                            $inventory->last_movement_at = now();
                            $inventory->updated_by = $actor;
                            $inventory->save();

                            InventoryMovement::query()->create([
                                'product_id' => $productId,
                                'inventory_id' => $inventory->id,
                                'movement_type' => InventoryMovementType::Sale,
                                'quantity' => -1 * abs($qty),
                                'unit_cost' => (float) ($products->get($productId)?->cost_price ?? 0),
                                'reference_type' => Sale::class,
                                'reference_id' => $sale->id,
                                'notes' => 'Venta '.$sale->sale_number,
                                'created_by' => $actor,
                            ]);
                        }

                        return $sale;
                    });
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Venta registrada')
                    ->body('Total '.self::formatMoney($documentTotal).' · '.$sale->sale_number)
                    ->success()
                    ->send();

                $nextUrl = config('fiscal.auto_print_on_sale_complete', true)
                    ? route('sales.fiscal-receipt.print', $sale)
                    : SaleResource::getUrl('view', ['record' => $sale], isAbsolute: false);

                return redirect()->to($nextUrl);
            });
    }

    private static function uniqueSaleNumber(): string
    {
        do {
            $number = 'VTA-'.now()->format('YmdHis').'-'.strtoupper(Str::random(5));
        } while (Sale::query()->where('sale_number', $number)->exists());

        return $number;
    }

    private static function formatMoney(float $amount): string
    {
        return '$'.number_format($amount, 2, '.', ',');
    }

    private static function formatBolivaresReference(float $usdAmount, Get $get): string
    {
        $rate = self::effectiveVesUsdRate($get);
        if ($rate <= 0) {
            return 'Bs. —';
        }

        $ves = round($usdAmount * $rate, 2);

        return 'Bs. '.number_format($ves, 2, ',', '.');
    }

    private static function formatBolivaresReferenceFromVes(float $vesAmount): string
    {
        return 'Bs. '.number_format(round($vesAmount, 2), 2, ',', '.');
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function resolvePaymentAmounts(float $documentTotalUsd, string $paymentMethod, float $mixedUsdPaid = 0, float $vesUsdRate = 0): array
    {
        $rate = max(0.0, $vesUsdRate);

        return match ($paymentMethod) {
            'efectivo_usd', 'transfer_usd', 'zelle' => [$documentTotalUsd, 0.0],
            'transfer_ves', 'pago_movil', 'efectivo_ves', 'punto_venta_ves' => [0.0, round($documentTotalUsd * $rate, 2)],
            'mixed' => [
                round(max(0.0, min($documentTotalUsd, $mixedUsdPaid)), 2),
                round(max(0.0, $documentTotalUsd - max(0.0, min($documentTotalUsd, $mixedUsdPaid))) * $rate, 2),
            ],
            default => [$documentTotalUsd, 0.0],
        };
    }

    private static function isUsdOnlyPaymentMethod(string $paymentMethod): bool
    {
        return match ($paymentMethod) {
            'transfer_ves', 'pago_movil', 'efectivo_ves', 'punto_venta_ves', 'mixed' => false,
            default => true,
        };
    }

    /**
     * @param  list<array{product: Product, quantity: float, inventory: Inventory}>  $lines
     * @return array{
     *     subtotal: float,
     *     tax_total: float,
     *     igtf_total: float,
     *     discount_total: float,
     *     document_total: float,
     *     ves_tax_fraction: float,
     *     per_line: list<array{line_subtotal: float, tax_amount: float, line_total: float}>,
     * }
     */
    private static function finalizePosPricingFromValidLines(
        array $lines,
        string $paymentMethod,
        float $discountRequested,
    ): array {
        if ($lines === []) {
            return [
                'subtotal' => 0.0,
                'tax_total' => 0.0,
                'igtf_total' => 0.0,
                'discount_total' => 0.0,
                'document_total' => 0.0,
                'ves_tax_fraction' => 0.0,
                'per_line' => [],
            ];
        }

        $lineGross = [];

        foreach ($lines as $line) {
            $product = $line['product'];
            $qty = $line['quantity'];
            $unit = (float) ($product->sale_price ?? 0);
            $lineGross[] = round($qty * $unit, 2);
        }

        $subtotal = round(array_sum($lineGross), 2);

        $discountTotal = self::isUsdOnlyPaymentMethod($paymentMethod)
            ? 0.0
            : max(0.0, round($discountRequested, 2));

        $discountTotal = min($discountTotal, $subtotal);

        $netMerchandise = round($subtotal - $discountTotal, 2);

        $ratio = $subtotal > 0.00001 ? $netMerchandise / $subtotal : 0.0;

        $lineNets = [];
        foreach ($lineGross as $g) {
            $lineNets[] = round($g * $ratio, 2);
        }

        $netsSum = round(array_sum($lineNets), 2);
        $drift = round($netMerchandise - $netsSum, 2);
        if ($lineNets !== [] && abs($drift) >= 0.001) {
            $last = count($lineNets) - 1;
            $lineNets[$last] = round($lineNets[$last] + $drift, 2);
        }

        $vatRate = DefaultVatRate::percent();
        $perLine = [];
        $taxTotal = 0.0;

        foreach ($lines as $i => $line) {
            /** @var Product $product */
            $product = $line['product'];
            $lineNet = $lineNets[$i] ?? 0.0;
            $appliesVat = (bool) ($product->applies_vat ?? false);
            $tax = $appliesVat && $vatRate > 0
                ? round($lineNet * $vatRate / 100, 2)
                : 0.0;
            $taxTotal += $tax;
            $perLine[] = [
                'line_subtotal' => $lineNet,
                'tax_amount' => $tax,
                'line_total' => round($lineNet + $tax, 2),
            ];
        }

        $taxTotal = round($taxTotal, 2);

        $invoiceBeforeIgtf = round($netMerchandise + $taxTotal, 2);

        $igtfTotal = 0.0;
        if ($paymentMethod === 'efectivo_usd') {
            $igtfRate = DefaultIgtfRate::percent();
            if ($igtfRate > 0.00001 && $invoiceBeforeIgtf > 0.00001) {
                $igtfTotal = round($invoiceBeforeIgtf * $igtfRate / 100, 2);
            }
        }

        $documentTotal = round($invoiceBeforeIgtf + $igtfTotal, 2);

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'igtf_total' => $igtfTotal,
            'discount_total' => $discountTotal,
            'document_total' => $documentTotal,
            'ves_tax_fraction' => 0.0,
            'per_line' => $perLine,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{product: Product, quantity: float, inventory: Inventory}>
     */
    private static function buildValidPosLinesFromRaw(array $rows, int $branchId): array
    {
        $valid = [];

        $productIds = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pid = $row['product_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);
            if (filled($pid) && $qty > 0) {
                $productIds[] = (int) $pid;
            }
        }

        self::warmPosDataForBranch($branchId, $productIds);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pid = $row['product_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);

            if (! filled($pid) || $qty <= 0) {
                continue;
            }

            $productId = (int) $pid;
            $product = self::posProduct($productId);

            if (! $product instanceof Product) {
                continue;
            }

            $inventory = self::posBranchInventory($branchId, $productId);
            if (! $inventory instanceof Inventory) {
                $inventory = self::ensurePosBranchInventoryRecord($branchId, $productId);
            }
            if (! $inventory instanceof Inventory) {
                continue;
            }

            $valid[] = [
                'product' => $product,
                'quantity' => $qty,
                'inventory' => $inventory,
            ];
        }

        return $valid;
    }

    /**
     * @return array{payment_usd: float, payment_ves: float}
     */
    private static function computePaymentBreakdownForForm(Get $get): array
    {
        $paymentMethod = (string) ($get('payment_method') ?? 'punto_venta_ves');
        $total = self::computeSaleTotal($get);
        $mixedUsdPaid = (float) ($get('mixed_usd_paid') ?? 0);
        $rate = self::effectiveVesUsdRate($get);
        [$paymentUsd, $paymentVes] = self::resolvePaymentAmounts($total, $paymentMethod, $mixedUsdPaid, $rate);

        return [
            'payment_usd' => $paymentUsd,
            'payment_ves' => $paymentVes,
        ];
    }

    /**
     * @return (
     *     array{
     *         subtotal: float,
     *         tax_total: float,
     *         igtf_total: float,
     *         discount_total: float,
     *         document_total: float,
     *         ves_tax_fraction: float,
     *         per_line: list<array{line_subtotal: float, tax_amount: float, line_total: float}>,
     *     }
     * )|null
     */
    private static function posPricingFromGet(Get $get): ?array
    {
        $branchId = Auth::user()?->branch_id;
        if (blank($branchId)) {
            return null;
        }

        $rows = $get('line_items') ?? [];
        if (! is_array($rows)) {
            return null;
        }

        $valid = self::buildValidPosLinesFromRaw($rows, (int) $branchId);
        if ($valid === []) {
            return null;
        }

        $paymentMethod = (string) ($get('payment_method') ?? 'punto_venta_ves');
        $discountRequested = (float) ($get('discount_total') ?? 0);

        return self::finalizePosPricingFromValidLines($valid, $paymentMethod, $discountRequested);
    }

    private static function computeSaleTotal(Get $get): float
    {
        $pricing = self::posPricingFromGet($get);

        return $pricing !== null ? $pricing['document_total'] : 0.0;
    }

    /**
     * Total de una línea desde el estado del ítem del repeater (precio lista × cantidad, sin impuestos).
     *
     * @param  array<string, mixed>  $rowState
     */
    private static function computeLineTotalFromRowState(array $rowState, Get $get): float
    {
        $branchId = Auth::user()?->branch_id;
        if (blank($branchId)) {
            return 0.0;
        }

        $productId = $rowState['product_id'] ?? null;
        $qty = (float) ($rowState['quantity'] ?? 0);
        if (! filled($productId) || $qty <= 0) {
            return 0.0;
        }

        self::warmPosDataForBranch((int) $branchId, [(int) $productId]);

        $inventory = self::posBranchInventory((int) $branchId, (int) $productId);
        if (! $inventory instanceof Inventory) {
            return 0.0;
        }

        $product = self::posProduct((int) $productId);
        if (! $product instanceof Product) {
            return 0.0;
        }

        $unit = (float) ($product->sale_price ?? 0);

        return round($qty * $unit, 2);
    }

    /**
     * Precarga productos e inventario de la sucursal para el POS (evita N+1).
     *
     * @param  list<int>  $productIds
     */
    private static function warmPosDataForBranch(int $branchId, array $productIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        /** @var array<int, Product|null> $productMap */
        $productMap = request()->attributes->get('cash_register.pos_products_by_id', []);
        if (! is_array($productMap)) {
            $productMap = [];
        }

        $missingProducts = array_values(array_diff($ids, array_keys($productMap)));
        if ($missingProducts !== []) {
            $select = [
                'id',
                'name',
                'barcode',
                'sale_price',
                'cost_price',
                'applies_vat',
            ];
            if (SchemaFacade::hasColumn('products', 'sku')) {
                $select[] = 'sku';
            }
            if (SchemaFacade::hasColumn('products', 'slug')) {
                $select[] = 'slug';
            }

            $fetchedProducts = Product::query()
                ->select($select)
                ->whereIn('id', $missingProducts)
                ->get()
                ->keyBy('id');

            foreach ($missingProducts as $pid) {
                $productMap[$pid] = $fetchedProducts->get($pid);
            }

            request()->attributes->set('cash_register.pos_products_by_id', $productMap);
        }

        if ($branchId <= 0) {
            return;
        }

        $invKey = 'cash_register.pos_inventory.'.$branchId;
        /** @var array<int, Inventory> $invMap */
        $invMap = request()->attributes->get($invKey, []);
        if (! is_array($invMap)) {
            $invMap = [];
        }

        $missingInv = array_values(array_diff($ids, array_keys($invMap)));
        if ($missingInv !== []) {
            $fetchedInv = Inventory::query()
                ->where('branch_id', $branchId)
                ->whereIn('product_id', $missingInv)
                ->get()
                ->keyBy('product_id');

            foreach ($missingInv as $pid) {
                $row = $fetchedInv->get($pid);
                if ($row instanceof Inventory) {
                    $invMap[$pid] = $row;
                }
            }

            request()->attributes->set($invKey, $invMap);
        }
    }

    /**
     * @deprecated Usar {@see warmPosDataForBranch}; se mantiene para llamadas que solo conocen productIds.
     *
     * @param  list<int>  $productIds
     */
    private static function warmPosProductsForIds(array $productIds): void
    {
        $branchId = Auth::user()?->branch_id;

        self::warmPosDataForBranch(filled($branchId) ? (int) $branchId : 0, $productIds);
    }

    private static function posBranchInventory(int $branchId, int $productId): ?Inventory
    {
        if ($branchId <= 0 || $productId <= 0) {
            return null;
        }

        self::warmPosDataForBranch($branchId, [$productId]);

        $invKey = 'cash_register.pos_inventory.'.$branchId;
        /** @var array<int, Inventory>|null $map */
        $map = request()->attributes->get($invKey);

        return is_array($map) && isset($map[$productId]) && $map[$productId] instanceof Inventory
            ? $map[$productId]
            : null;
    }

    private static function posProduct(int $id): ?Product
    {
        if ($id <= 0) {
            return null;
        }

        self::warmPosProductsForIds([$id]);

        /** @var array<int, Product|null>|null $map */
        $map = request()->attributes->get('cash_register.pos_products_by_id');

        return is_array($map) && array_key_exists($id, $map) && $map[$id] instanceof Product
            ? $map[$id]
            : null;
    }

    /**
     * Coincidencia exacta en catálogo (misma prioridad que compras: barras, SKU, slug).
     */
    private static function resolvePosProductIdByExactCatalogCode(string $term): ?int
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $base = Product::query()->where('is_active', true);

        $id = (clone $base)->where('barcode', $term)->value('id');
        if (filled($id)) {
            return (int) $id;
        }

        if (SchemaFacade::hasColumn('products', 'sku')) {
            $id = (clone $base)->where('sku', $term)->value('id');
            if (filled($id)) {
                return (int) $id;
            }
        }

        if (SchemaFacade::hasColumn('products', 'slug')) {
            $id = (clone $base)->where('slug', $term)->value('id');
            if (filled($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    private static function mergePosInventoryCache(int $branchId, int $productId, Inventory $inventory): void
    {
        $invKey = 'cash_register.pos_inventory.'.$branchId;
        /** @var array<int, Inventory> $map */
        $map = request()->attributes->get($invKey, []);
        if (! is_array($map)) {
            $map = [];
        }
        $map[$productId] = $inventory;
        request()->attributes->set($invKey, $map);
    }

    /**
     * Garantiza fila de inventario en la sucursal (p. ej. producto recién dado de alta o solo comprado en otra sede).
     */
    private static function ensurePosBranchInventoryRecord(int $branchId, int $productId): ?Inventory
    {
        if ($branchId <= 0 || $productId <= 0) {
            return null;
        }

        $existing = Inventory::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->first();

        if ($existing instanceof Inventory) {
            self::mergePosInventoryCache($branchId, $productId, $existing);

            return $existing;
        }

        $actor = Auth::user()?->email
            ?? Auth::user()?->name
            ?? 'sistema';

        $inventory = Inventory::query()->firstOrCreate(
            [
                'branch_id' => $branchId,
                'product_id' => $productId,
            ],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
                'allow_negative_stock' => false,
                'created_by' => $actor,
                'updated_by' => $actor,
            ],
        );

        self::mergePosInventoryCache($branchId, $productId, $inventory);

        return $inventory;
    }

    /**
     * Búsqueda POS: JOIN directo (sin whereHas anidado), coincidencia exacta por código de barras primero.
     *
     * @return array<int, string>
     */
    private static function searchInventoryProductsForBranch(int $branchId, string $search): array
    {
        $term = trim($search);

        if ($term !== '') {
            $exactProductId = DB::table('inventories')
                ->join('products', 'products.id', '=', 'inventories.product_id')
                ->where('inventories.branch_id', $branchId)
                ->where('products.is_active', true)
                ->whereNotNull('inventories.product_id')
                ->where('products.barcode', $term)
                ->value('products.id');

            if (blank($exactProductId) && SchemaFacade::hasColumn('products', 'sku')) {
                $exactProductId = DB::table('inventories')
                    ->join('products', 'products.id', '=', 'inventories.product_id')
                    ->where('inventories.branch_id', $branchId)
                    ->where('products.is_active', true)
                    ->whereNotNull('inventories.product_id')
                    ->where('products.sku', $term)
                    ->value('products.id');
            }

            if (blank($exactProductId) && SchemaFacade::hasColumn('products', 'slug')) {
                $exactProductId = DB::table('inventories')
                    ->join('products', 'products.id', '=', 'inventories.product_id')
                    ->where('inventories.branch_id', $branchId)
                    ->where('products.is_active', true)
                    ->whereNotNull('inventories.product_id')
                    ->where('products.slug', $term)
                    ->value('products.id');
            }

            // Misma lógica que la compra: producto activo en catálogo aunque aún no tenga fila en inventarios de esta sucursal.
            if (blank($exactProductId)) {
                $catalogId = self::resolvePosProductIdByExactCatalogCode($term);
                if ($catalogId !== null) {
                    $exactProductId = $catalogId;
                }
            }

            if (filled($exactProductId)) {
                $id = (int) $exactProductId;
                $selectCols = ['id', 'name', 'barcode'];
                if (SchemaFacade::hasColumn('products', 'sku')) {
                    $selectCols[] = 'sku';
                }

                $row = DB::table('products')
                    ->select($selectCols)
                    ->where('id', $id)
                    ->first();

                if ($row !== null) {
                    self::ensurePosBranchInventoryRecord($branchId, $id);
                    self::warmPosDataForBranch($branchId, [$id]);
                    $label = filled($row->barcode)
                        ? $row->barcode.' · '.$row->name
                        : $row->name;
                    $prod = self::posProduct($id);
                    if ($prod instanceof Product) {
                        $label .= ' · '.self::formatMoney((float) ($prod->sale_price ?? 0));
                    }
                    $inv = self::posBranchInventory($branchId, $id);
                    if (! $inv instanceof Inventory || (float) $inv->quantity <= 0) {
                        $label .= ' · Sin existencias en sucursal (stock 0 hasta recepción)';
                    }

                    return [$id => $label];
                }
            }
        }

        $selectList = ['products.id', 'products.name', 'products.barcode'];
        if (SchemaFacade::hasColumn('products', 'sku')) {
            $selectList[] = 'products.sku';
        }
        if (SchemaFacade::hasColumn('products', 'slug')) {
            $selectList[] = 'products.slug';
        }

        $query = DB::table('inventories')
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->where('inventories.branch_id', $branchId)
            ->where('products.is_active', true)
            ->whereNotNull('inventories.product_id')
            ->select($selectList)
            ->orderBy('products.name')
            ->limit($term === '' ? 25 : 40);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $ingredientLike = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
            $query->where(function ($w) use ($like, $ingredientLike): void {
                $w->where('products.name', 'like', $like)
                    ->orWhere('products.barcode', 'like', $like)
                    ->orWhereRaw('LOWER(products.active_ingredient) LIKE ?', [$ingredientLike]);

                if (SchemaFacade::hasColumn('products', 'sku')) {
                    $w->orWhere('products.sku', 'like', $like);
                }

                if (SchemaFacade::hasColumn('products', 'slug')) {
                    $w->orWhere('products.slug', 'like', $like);
                }
            });
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        self::warmPosDataForBranch($branchId, $ids);

        return $rows->mapWithKeys(function ($row) use ($branchId): array {
            $id = (int) $row->id;
            $label = filled($row->barcode)
                ? $row->barcode.' · '.$row->name
                : $row->name;
            $inv = self::posBranchInventory($branchId, $id);
            $prod = self::posProduct($id);
            if ($inv instanceof Inventory && $prod instanceof Product) {
                $label .= ' · '.self::formatMoney((float) ($prod->sale_price ?? 0));
            }

            return [$id => $label];
        })->all();
    }

    /**
     * Estado inicial del formulario de caja (modal).
     *
     * @return array<string, mixed>
     */
    private static function defaultPosFormState(): array
    {
        return array_merge([
            'client_id' => null,
            'payment_method' => 'punto_venta_ves',
            'card_last4' => null,
            'mixed_usd_paid' => null,
            'reference' => null,
            'discount_total' => 0.0,
            'line_items' => [
                [
                    'product_id' => null,
                    'quantity' => 1,
                ],
            ],
        ], self::initialDolarFormState());
    }

    /**
     * Abre el select de producto en un ítem del repeater y enfoca la búsqueda.
     *
     * @param  bool  $pickFirstItem  true = primera línea (tras elegir cliente); false = última (nueva línea).
     */
    private static function focusPosLineProductSearchJs(bool $pickFirstItem): string
    {
        $targetExpr = $pickFirstItem ? 'items[0]' : 'items[items.length - 1]';
        $outerMs = $pickFirstItem ? 160 : 320;
        $innerMs = $pickFirstItem ? 90 : 160;

        return <<<JS
            setTimeout(() => {
                const wrap = document.querySelector('.farmadoc-pos-line-items-repeater');
                if (! wrap) {
                    return;
                }
                let items = wrap.querySelectorAll('.fi-fo-repeater-item');
                if (! items.length) {
                    items = wrap.querySelectorAll('table tbody tr');
                }
                const target = {$targetExpr};
                if (! target) {
                    return;
                }
                const btn = target.querySelector('.fi-select-input-btn');
                if (! btn || typeof btn.click !== 'function') {
                    return;
                }
                btn.click();
                setTimeout(() => {
                    const modal = document.querySelector('.fi-modal-window');
                    let panel = modal?.querySelector('.fi-dropdown-panel');
                    if (! panel) {
                        panel = document.querySelector('.fi-dropdown-panel');
                    }
                    const input = panel?.querySelector(
                        'input.fi-input, input[type="search"], input[role="combobox"], input:not([type="hidden"])',
                    );
                    input?.focus?.();
                }, {$innerMs});
            }, {$outerMs});
            JS;
    }

    /**
     * POS: cuando un lector llena el código y hay resultado exacto, confirma automáticamente la opción
     * (equivalente a Enter) para que el flujo pase a la siguiente línea del repeater sin intervención manual.
     */
    private static function mountPosBarcodeAutoAdvanceJs(): string
    {
        return <<<'JS'
            (() => {
                if (window.__farmadocPosBarcodeAutoAdvanceMounted) {
                    return;
                }
                window.__farmadocPosBarcodeAutoAdvanceMounted = true;

                const BARCODE_RE = /^[0-9A-Za-z\-]{4,}$/;
                let debounceId = null;

                const triggerAutoConfirm = (input) => {
                    const value = String(input?.value ?? '').trim();
                    if (!BARCODE_RE.test(value)) {
                        return;
                    }

                    const modal = document.querySelector('.fi-modal-window');
                    if (!modal || !modal.querySelector('.farmadoc-pos-line-items-repeater')) {
                        return;
                    }

                    const hasOpenSelect = modal.querySelector(
                        '.farmadoc-pos-line-items-repeater .fi-select-input-btn[aria-expanded="true"]',
                    );
                    if (!hasOpenSelect) {
                        return;
                    }

                    input.dispatchEvent(
                        new KeyboardEvent('keydown', {
                            key: 'Enter',
                            code: 'Enter',
                            bubbles: true,
                            cancelable: true,
                        }),
                    );
                };

                document.addEventListener(
                    'input',
                    (ev) => {
                        const target = ev.target;
                        if (!(target instanceof HTMLInputElement)) {
                            return;
                        }

                        const panel = target.closest('.fi-dropdown-panel');
                        if (!panel) {
                            return;
                        }

                        if (debounceId) {
                            clearTimeout(debounceId);
                        }
                        debounceId = setTimeout(() => triggerAutoConfirm(target), 90);
                    },
                    true,
                );
            })();
        JS;
    }

    /**
     * Modal «Cliente»: abre el select y enfoca la búsqueda al montar.
     */
    private static function mountPosClientGateFocusSelectJs(): string
    {
        return <<<'JS'
            setTimeout(() => {
                const wrap = document.querySelector('.farmadoc-pos-client-gate-select');
                if (! wrap) {
                    return;
                }
                const btn = wrap.querySelector('.fi-select-input-btn');
                if (! btn || typeof btn.click !== 'function') {
                    return;
                }
                btn.click();
                setTimeout(() => {
                    const modal = document.querySelector('.fi-modal-window');
                    let panel = modal?.querySelector('.fi-dropdown-panel');
                    if (! panel) {
                        panel = document.querySelector('.fi-dropdown-panel');
                    }
                    const input = panel?.querySelector(
                        'input.fi-input, input[type="search"], input[role="combobox"], input:not([type="hidden"])',
                    );
                    input?.focus?.();
                }, 120);
            }, 200);
        JS;
    }

    /**
     * Alta rápida desde la modal de caja: cumple columnas obligatorias de {@see Client} con valores neutros.
     */
    private static function createClientFromPosQuickForm(string $name, string $documentNumber, string $phone): Client
    {
        $user = Auth::user();
        $actor = filled($user?->email)
            ? (string) $user->email
            : (filled($user?->name) ? (string) $user->name : 'pos');

        return Client::query()->create([
            'name' => $name,
            'document_type' => 'CC',
            'document_number' => $documentNumber,
            'email' => 'pos+'.Str::uuid()->toString().'@mostrador.invalid',
            'phone' => $phone,
            'address' => '—',
            'city' => '—',
            'state' => '—',
            'country' => 'Colombia',
            'status' => 'active',
            'customer_discount' => 0,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);
    }

    /**
     * @return array<int|string, string>
     */
    private static function posClientSearchResults(string $search): array
    {
        $term = trim($search);
        if ($term === '') {
            return Client::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->limit(30)
                ->select(['id', 'name', 'document_number'])
                ->get()
                ->mapWithKeys(fn (Client $client): array => [
                    $client->id => $client->name.(filled($client->document_number) ? ' · '.$client->document_number : ''),
                ])
                ->all();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return Client::query()
            ->where('status', 'active')
            ->where(function ($query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('document_number', 'like', $like);
            })
            ->orderBy('name')
            ->limit(30)
            ->select(['id', 'name', 'document_number'])
            ->get()
            ->mapWithKeys(fn (Client $client): array => [
                $client->id => $client->name.(filled($client->document_number) ? ' · '.$client->document_number : ''),
            ])
            ->all();
    }

    private static function posClientOptionLabel(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $client = Client::query()
            ->select(['id', 'name', 'document_number'])
            ->find((int) $value);
        if (! $client) {
            return null;
        }

        return $client->name.(filled($client->document_number) ? ' · '.$client->document_number : '');
    }

    /**
     * @return array{ves_usd_rate: ?float, ves_usd_rate_manual: null}
     */
    private static function initialDolarFormState(): array
    {
        $estadoOk = app(DolarApiEstadoService::class)->isAvailable();
        $rate = $estadoOk ? app(DolarApiDolaresService::class)->getOfficialUsdToVesRate() : null;
        $apiOk = $rate !== null && $rate > 0;

        return [
            'ves_usd_rate' => $apiOk ? $rate : null,
            'ves_usd_rate_manual' => null,
        ];
    }

    private static function hasValidApiRate(Get $get): bool
    {
        $api = $get('ves_usd_rate');

        return is_numeric($api) && (float) $api > 0;
    }

    private static function effectiveVesUsdRate(Get $get): float
    {
        $api = $get('ves_usd_rate');
        if (is_numeric($api) && (float) $api > 0) {
            return (float) $api;
        }

        $manual = $get('ves_usd_rate_manual');
        if (is_numeric($manual) && (float) $manual > 0) {
            return (float) $manual;
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function effectiveVesUsdRateFromData(array $data): float
    {
        $api = $data['ves_usd_rate'] ?? null;
        if (is_numeric($api) && (float) $api > 0) {
            return (float) $api;
        }

        $manual = $data['ves_usd_rate_manual'] ?? null;
        if (is_numeric($manual) && (float) $manual > 0) {
            return (float) $manual;
        }

        return 0.0;
    }

    private static function requiresVesConversion(string $paymentMethod, float $documentTotalUsd, float $mixedUsdPaid): bool
    {
        return match ($paymentMethod) {
            'transfer_ves', 'pago_movil', 'efectivo_ves', 'punto_venta_ves' => true,
            'mixed' => max(0.0, $documentTotalUsd - min($documentTotalUsd, max(0.0, $mixedUsdPaid))) > 0.00001,
            default => false,
        };
    }

    private static function buildPosTotalsBreakdownHtml(Get $get): HtmlString
    {
        $p = self::posPricingFromGet($get);
        if ($p === null) {
            return new HtmlString('');
        }

        $lines = [];
        $lines[] = 'Subtotal '.self::formatMoney($p['subtotal']);

        if ($p['discount_total'] > 0.00001) {
            $lines[] = 'Descuento −'.self::formatMoney($p['discount_total']);
        }

        if ($p['tax_total'] > 0.00001) {
            $lines[] = 'IVA ('.rtrim(rtrim(number_format(DefaultVatRate::percent(), 2, '.', ''), '0'), '.').'%) '.self::formatMoney($p['tax_total']);
        }

        if ($p['igtf_total'] > 0.00001) {
            $lines[] = 'IGTF ('.rtrim(rtrim(number_format(DefaultIgtfRate::percent(), 2, '.', ''), '0'), '.').'%) '.self::formatMoney($p['igtf_total']);
        }

        $html = '<div class="farmadoc-pos-breakdown space-y-0.5">';
        foreach ($lines as $line) {
            $html .= '<div>'.e($line).'</div>';
        }
        $html .= '</div>';

        return new HtmlString($html);
    }

    private static function buildTotalVesBannerHtml(Get $get): HtmlString
    {
        $apiRate = $get('ves_usd_rate');
        $hasApi = is_numeric($apiRate) && (float) $apiRate > 0;
        $manual = $get('ves_usd_rate_manual');
        $hasManual = is_numeric($manual) && (float) $manual > 0;

        $pillClass = $hasApi
            ? 'farmadoc-pos-rate-pill farmadoc-pos-rate-pill--ok'
            : 'farmadoc-pos-rate-pill farmadoc-pos-rate-pill--error';

        if ($hasApi) {
            $rateLabel = '1 USD = Bs. '.number_format((float) $apiRate, 2, ',', '.').' (API oficial)';
        } elseif ($hasManual) {
            $rateLabel = '1 USD = Bs. '.number_format((float) $manual, 2, ',', '.').' (manual)';
        } else {
            $rateLabel = 'Sin tasa · ingrese manualmente';
        }

        $vesLine = self::formatBolivaresReference(self::computeSaleTotal($get), $get);
        $hint = $hasApi
            ? 'Fuente: ve.dolarapi.com (oficial)'
            : 'API no disponible — use tasa manual abajo';

        $bcvLogoSrc = e(asset('images/logos/logoBCV.png'));

        return new HtmlString(
            '<p class="'.$pillClass.' farmadoc-pos-rate-pill--with-bcv" role="group" aria-label="Tasa referencial BCV">'.
            '<img src="'.$bcvLogoSrc.'" alt="BCV" class="farmadoc-pos-bcv-logo" loading="lazy" decoding="async" />'.
            '<span class="farmadoc-pos-rate-pill__text">'.e($rateLabel).'</span>'.
            '</p>'.
            '<p class="farmadoc-pos-total-ios__ves">≈ '.e($vesLine).'</p>'.
            '<p class="farmadoc-pos-total-ios__hint">'.e($hint).'</p>'
        );
    }
}
