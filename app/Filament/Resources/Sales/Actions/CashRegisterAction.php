<?php

namespace App\Filament\Resources\Sales\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\ProductTransferStatus;
use App\Enums\SaleStatus;
use App\Enums\VenezuelanPagoMovilBank;
use App\Filament\Resources\Sales\SaleResource;
use App\Http\Requests\BdvConciliation\GetMovementRequest;
use App\Models\Client;
use App\Models\FarmaExpressCostStructure;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTransfer;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\BdvConciliation\BdvConciliationClient;
use App\Services\Dolar\DolarApiDolaresService;
use App\Services\Dolar\DolarApiEstadoService;
use App\Services\Finance\AccountsReceivableFromSaleRegistrar;
use App\Support\Finance\DefaultIgtfRate;
use App\Support\Finance\DefaultVatRate;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\BasePage;
use Filament\Schemas\Components\Fieldset;
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
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component as LivewireComponent;
use RuntimeException;
use Throwable;

final class CashRegisterAction
{
    /** Nombre Livewire/Filament de {@see self::makeClientGate()} (accesos directos, query `abrir=caja`, etc.). */
    public const CLIENT_GATE_ACTION_NAME = 'posClientGate';

    public const REGISTER_ACTION_NAME = 'posRegister';

    public const PAGO_MOVIL_CONCILIATION_ACTION_NAME = 'posPagoMovilConciliation';

    public const CREDIT_SALE_CONFIRMATION_ACTION_NAME = 'posCreditSaleConfirmation';

    /**
     * Primer paso: buscar cliente (nombre o documento). Al elegir uno se abre la caja; «Continuar» sin elegir = mostrador.
     */
    public static function makeClientGate(): Action
    {
        return Action::make(self::CLIENT_GATE_ACTION_NAME)
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

                                $clientId = (int) $state;
                                $pickedLabel = Client::query()->whereKey($clientId)->value('name');
                                AuditLogger::record(
                                    'pos_caja_client_picked_from_catalog',
                                    'Caja · Cliente elegido desde catálogo',
                                    Client::class,
                                    $clientId,
                                    filled($pickedLabel) ? (string) $pickedLabel : null,
                                    ['module' => 'pos_caja', 'via' => 'busqueda_instantanea'],
                                );

                                $livewire = $component->getLivewire();
                                if (! is_object($livewire) || ! method_exists($livewire, 'replaceMountedAction')) {
                                    return;
                                }

                                $livewire->replaceMountedAction('posRegister', [
                                    'client_id' => $clientId,
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
                    $clientId = (int) $cid;
                    $pickedLabel = Client::query()->whereKey($clientId)->value('name');
                    AuditLogger::record(
                        'pos_caja_client_picked_from_catalog',
                        'Caja · Cliente elegido desde catálogo',
                        Client::class,
                        $clientId,
                        filled($pickedLabel) ? (string) $pickedLabel : null,
                        ['module' => 'pos_caja', 'via' => 'continuar_modal'],
                    );
                    $livewire->replaceMountedAction('posRegister', [
                        'client_id' => $clientId,
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
                        $docDigits = preg_replace('/\D+/', '', $quickDoc) ?? '';
                        AuditLogger::record(
                            'pos_caja_quick_client_existing_doc',
                            'Caja · Registro rápido: documento ya existente; se usa cliente registrado',
                            Client::class,
                            $existing->id,
                            $existing->name,
                            [
                                'module' => 'pos_caja',
                                'document_last4' => strlen($docDigits) >= 4 ? substr($docDigits, -4) : null,
                            ],
                        );
                        $livewire->replaceMountedAction('posRegister', [
                            'client_id' => $existing->id,
                        ]);

                        return;
                    }

                    $client = self::createClientFromPosQuickForm($quickName, $quickDoc, $quickPhone);
                    AuditLogger::record(
                        'pos_caja_quick_client_created',
                        'Caja · Cliente nuevo desde registro rápido',
                        Client::class,
                        $client->id,
                        $client->name,
                        ['module' => 'pos_caja'],
                    );
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

                AuditLogger::record(
                    'pos_caja_walk_in',
                    'Caja · Inicio de venta mostrador (sin cliente)',
                    properties: ['module' => 'pos_caja'],
                );
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
        return Action::make(self::REGISTER_ACTION_NAME)
            ->label('Caja registradora')
            ->modalHeading('Caja registradora')
            ->modalDescription('Cargue productos, revise totales y confirme el cobro.')
            ->modalIcon(Heroicon::Banknotes)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Registrar venta')
            ->modalCancelAction(fn (Action $action): Action => $action->color('danger'))
            ->registerModalActions([
                self::makeCreditSaleConfirmation(),
                self::makePagoMovilConciliation(),
            ])
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
            ])
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $args = $action->getArguments();
                $clientId = $args['client_id'] ?? null;

                $state = self::defaultPosFormState();
                if (is_array($args['pos_data'] ?? null)) {
                    $state = array_merge($state, $args['pos_data']);
                }
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
                            ->icon(Heroicon::ShoppingCart)
                            ->iconColor('primary')
                            ->extraAttributes([
                                'class' => 'farmadoc-pos-cart-section',
                            ])
                            ->schema([
                                Hidden::make('client_id'),
                                Hidden::make('bdv_pm_conciliated')
                                    ->default(false),
                                Hidden::make('credit_sale_confirmed')
                                    ->default(false),
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
                                    ->weight(FontWeight::SemiBold)
                                    ->size(TextSize::Large)
                                    ->dehydrated(false)
                                    ->icon(Heroicon::User)
                                    ->iconColor('primary')
                                    ->extraAttributes([
                                        'class' => 'rounded-xl bg-primary-500/10 px-3 py-2 text-primary-50 dark:bg-primary-500/15',
                                    ])
                                    ->columnSpanFull(),
                                Select::make('pos_sale_transfer_id')
                                    ->label('Buscador de Traslados de Venta (codigo del traslado)')
                                    ->placeholder('Ej. TV-260002 o parte del código')
                                    ->live()
                                    ->searchable()
                                    ->searchDebounce(150)
                                    ->getSearchResultsUsing(fn (string $search): array => self::posSaleTransferSearchResults($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => self::posSaleTransferOptionLabel($value))
                                    ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                        if (! filled($state)) {
                                            return;
                                        }

                                        self::loadPosLineItemsFromSaleTransfer((int) $state, $set);

                                        $livewire = $component->getLivewire();
                                        if ($livewire instanceof LivewireComponent) {
                                            $livewire->js(self::focusPosLineProductSearchJs(pickFirstItem: false));
                                        }
                                    })
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Truck)
                                    ->columnSpanFull(),
                                Select::make('pos_product_search')
                                    ->label('Buscador General de Productos (Nombre, Codigo, PA)')
                                    ->placeholder('Código, nombre o principio activo')
                                    ->searchPrompt('Escriba nombre, principio activo o código')
                                    ->live()
                                    ->searchable()
                                    ->searchDebounce(150)
                                    ->disabled(fn (): bool => blank(Auth::user()?->branch_id))
                                    ->native(false)
                                    ->getSearchResultsUsing(function (string $search, Get $get): array {
                                        $branchId = Auth::user()?->branch_id;
                                        if (blank($branchId)) {
                                            return [];
                                        }

                                        return self::searchInventoryProductsForBranch((int) $branchId, $search, $get);
                                    })
                                    ->getOptionLabelUsing(function ($value, Get $get): ?string {
                                        if (blank($value)) {
                                            return null;
                                        }

                                        $branchId = Auth::user()?->branch_id;
                                        if (blank($branchId)) {
                                            return null;
                                        }

                                        return self::buildPosSearchOptionLabelFromCatalog((int) $branchId, (int) $value, $get);
                                    })
                                    ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                        if (! filled($state)) {
                                            return;
                                        }

                                        $branchId = Auth::user()?->branch_id;
                                        if (blank($branchId)) {
                                            return;
                                        }

                                        self::appendProductToPosLineItems(
                                            branchId: (int) $branchId,
                                            productId: (int) $state,
                                            set: $set,
                                            get: $get,
                                        );
                                        self::ensurePosTrailingEmptyLine($set, $get);

                                        $set('pos_product_search', null);

                                        $livewire = $component->getLivewire();
                                        if ($livewire instanceof LivewireComponent) {
                                            $livewire->js(self::focusPosLineProductSearchJs(pickFirstItem: false));
                                        }
                                    })
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
                                        $branchId = Auth::user()?->branch_id;
                                        if ($product instanceof Product && filled($branchId)) {
                                            $title .= self::posProductBolivaresAndBranchStockSuffix((int) $branchId, $product, $get);
                                        }
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
                                            ->getSearchResultsUsing(function (string $search, Get $get): array {
                                                $branchId = Auth::user()?->branch_id;
                                                if (blank($branchId)) {
                                                    return [];
                                                }

                                                return self::searchInventoryProductsForBranch((int) $branchId, $search, $get);
                                            })
                                            ->getOptionLabelUsing(function ($value, Get $get): ?string {
                                                if (blank($value)) {
                                                    return null;
                                                }

                                                $branchId = Auth::user()?->branch_id;
                                                if (blank($branchId)) {
                                                    return null;
                                                }

                                                $id = (int) $value;

                                                return self::buildPosSearchOptionLabelFromCatalog((int) $branchId, $id, $get)
                                                    ?? ('Producto #'.$id);
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

                                                $branchId = Auth::user()?->branch_id;
                                                if (blank($branchId)) {
                                                    return;
                                                }

                                                $keys = array_keys($lineItems);
                                                $lastKey = $keys[array_key_last($keys)];

                                                if ((string) $currentItemKey !== (string) $lastKey) {
                                                    return;
                                                }

                                                $keysList = array_values($keys);
                                                $newProductId = (int) $state;
                                                self::warmPosDataForBranch((int) $branchId, [$newProductId]);

                                                $selectedProduct = self::posProduct($newProductId);
                                                $selectedProductLabel = $selectedProduct instanceof Product
                                                    ? $selectedProduct->name
                                                    : 'Producto #'.$newProductId;
                                                $availableForSelectedProduct = self::posAvailableQuantity((int) $branchId, $newProductId);
                                                if ($availableForSelectedProduct !== null && $availableForSelectedProduct <= 0.0001) {
                                                    self::notifyPosStockZero($selectedProductLabel);
                                                    $set("{$lineItemsPath}.{$currentItemKey}.product_id", null, isAbsolute: true);
                                                    $set("{$lineItemsPath}.{$currentItemKey}.quantity", 1, isAbsolute: true);

                                                    if ($livewire instanceof LivewireComponent) {
                                                        $livewire->js(self::focusPosLineProductSearchJs(pickFirstItem: false));
                                                    }

                                                    return;
                                                }

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

                                                    if ($availableForSelectedProduct !== null && $mergedQty > ($availableForSelectedProduct + 0.0001)) {
                                                        self::notifyPosQuantityExceedsStock(
                                                            $selectedProductLabel,
                                                            $mergedQty,
                                                            $availableForSelectedProduct,
                                                        );
                                                        $set("{$lineItemsPath}.{$currentItemKey}.product_id", null, isAbsolute: true);
                                                        $set("{$lineItemsPath}.{$currentItemKey}.quantity", 1, isAbsolute: true);

                                                        if ($livewire instanceof LivewireComponent) {
                                                            $livewire->js(self::focusPosLineProductSearchJs(pickFirstItem: false));
                                                        }

                                                        return;
                                                    }

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
                                            ->afterStateUpdated(function (mixed $state, Get $get): void {
                                                $branchId = Auth::user()?->branch_id;
                                                if (blank($branchId)) {
                                                    return;
                                                }

                                                $productId = $get('product_id');
                                                if (! filled($productId)) {
                                                    return;
                                                }

                                                $pid = (int) $productId;
                                                self::warmPosDataForBranch((int) $branchId, [$pid]);

                                                $requestedQuantity = max(0.0, (float) $state);
                                                $available = self::posAvailableQuantity((int) $branchId, $pid);
                                                if ($available === null || $requestedQuantity <= ($available + 0.0001)) {
                                                    return;
                                                }

                                                $product = self::posProduct($pid);
                                                self::notifyPosQuantityExceedsStock(
                                                    $product instanceof Product ? $product->name : 'Producto #'.$pid,
                                                    $requestedQuantity,
                                                    $available,
                                                );
                                            })
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
                                                        $branchId = Auth::user()?->branch_id;
                                                        $productId = $get('product_id');
                                                        if (filled($branchId) && filled($productId)) {
                                                            $pid = (int) $productId;
                                                            self::warmPosDataForBranch((int) $branchId, [$pid]);
                                                            $available = self::posAvailableQuantity((int) $branchId, $pid);
                                                            if ($available !== null && $next > ($available + 0.0001)) {
                                                                $product = self::posProduct($pid);
                                                                self::notifyPosQuantityExceedsStock(
                                                                    $product instanceof Product ? $product->name : 'Producto #'.$pid,
                                                                    $next,
                                                                    $available,
                                                                );

                                                                return;
                                                            }
                                                        }

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
                                                    ->minValue(0.000001)
                                                    ->step(0.000001)
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
                                                'credito_cliente' => 'Crédito · cuenta por cobrar',
                                            ])
                                            ->default('punto_venta_ves')
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                                if ($state === 'credito_cliente') {
                                                    $set('generate_accounts_receivable', true);
                                                    $set('bdv_pm_conciliated', false);

                                                    return;
                                                }

                                                $set('generate_accounts_receivable', false);

                                                if (
                                                    $state === 'pago_movil'
                                                    || ($state === 'mixed' && self::selectedMixedVesPaymentMethodFromGet($get) === 'pago_movil')
                                                ) {
                                                    $set('bdv_pm_conciliated', false);
                                                    $livewire = $component->getLivewire();
                                                    if ($livewire instanceof HasActions) {
                                                        $paymentVes = self::computePaymentBreakdownForForm($get)['payment_ves'];
                                                        if ($paymentVes <= 0.00001) {
                                                            return;
                                                        }

                                                        $reference = trim((string) ($get('reference') ?? ''));
                                                        $livewire->mountAction(self::PAGO_MOVIL_CONCILIATION_ACTION_NAME, [
                                                            'pos_data' => [
                                                                'reference' => $reference,
                                                                'client_id' => filled($get('client_id')) ? (int) $get('client_id') : null,
                                                                'generate_accounts_receivable' => false,
                                                            ],
                                                            'payment_ves' => $paymentVes,
                                                        ]);
                                                    }

                                                    return;
                                                }

                                                $set('bdv_pm_conciliated', false);
                                            })
                                            ->native(false)
                                            ->prefixIcon(Heroicon::CreditCard),
                                        Toggle::make('generate_accounts_receivable')
                                            ->label('Vender a Credito')
                                            ->default(false)
                                            ->inline(true)
                                            ->live()
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                                if (filter_var($state, FILTER_VALIDATE_BOOLEAN)) {
                                                    $set('payment_method', 'credito_cliente');
                                                    $set('bdv_pm_conciliated', false);

                                                    return;
                                                }

                                                if (($get('payment_method') ?? '') === 'credito_cliente') {
                                                    $set('payment_method', 'punto_venta_ves');
                                                }
                                            })
                                            ->columnSpanFull()
                                            ->extraFieldWrapperAttributes([
                                                'class' => 'rounded-xl border border-zinc-300/60 bg-zinc-50/30 px-3 py-2 dark:border-white/20 dark:bg-white/5',
                                            ]),
                                        TextInput::make('card_last4')
                                            ->label('Últimos 4 dígitos de la tarjeta')
                                            ->helperText('recibe 4 digitos')
                                            ->inputMode('numeric')
                                            // Sin ->required(): evita el atributo HTML `required` y el tooltip nativo del navegador (poco contraste).
                                            ->markAsRequired(function (Get $get): bool {
                                                if ($get('payment_method') === 'punto_venta_ves') {
                                                    return true;
                                                }

                                                if ($get('payment_method') !== 'mixed') {
                                                    return false;
                                                }

                                                return self::selectedMixedVesPaymentMethodFromGet($get) === 'punto_venta_ves'
                                                    && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001;
                                            })
                                            ->rules(fn (Get $get): array => [
                                                Rule::requiredIf(function () use ($get): bool {
                                                    if ($get('payment_method') === 'punto_venta_ves') {
                                                        return true;
                                                    }

                                                    if ($get('payment_method') !== 'mixed') {
                                                        return false;
                                                    }

                                                    return self::selectedMixedVesPaymentMethodFromGet($get) === 'punto_venta_ves'
                                                        && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001;
                                                }),
                                                'regex:/^\d{4}$/',
                                            ])
                                            ->regex('/^\d{4}$/')
                                            ->validationMessages([
                                                'required_if' => 'Debe indicar los últimos 4 dígitos de la tarjeta para Punto de Venta.',
                                                'required' => 'Debe indicar los últimos 4 dígitos de la tarjeta para Punto de Venta.',
                                                'regex' => 'Ingrese exactamente 4 dígitos numéricos.',
                                            ])
                                            ->visible(function (Get $get): bool {
                                                if ($get('payment_method') === 'punto_venta_ves') {
                                                    return true;
                                                }

                                                if ($get('payment_method') !== 'mixed') {
                                                    return false;
                                                }

                                                return self::selectedMixedVesPaymentMethodFromGet($get) === 'punto_venta_ves'
                                                    && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001;
                                            }),
                                        TextInput::make('mixed_usd_paid')
                                            ->label('Pago en USD (usuario)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->prefix('$')
                                            ->live(debounce: 300)
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, TextInput $component): void {
                                                if ($get('payment_method') !== 'mixed') {
                                                    return;
                                                }

                                                $set('bdv_pm_conciliated', false);

                                                if (self::selectedMixedVesPaymentMethodFromGet($get) !== 'pago_movil') {
                                                    return;
                                                }

                                                $livewire = $component->getLivewire();
                                                if (! $livewire instanceof HasActions) {
                                                    return;
                                                }

                                                $paymentVes = self::computePaymentBreakdownForForm($get)['payment_ves'];
                                                if ($paymentVes <= 0.00001) {
                                                    return;
                                                }

                                                $reference = trim((string) ($get('reference') ?? ''));
                                                $livewire->mountAction(self::PAGO_MOVIL_CONCILIATION_ACTION_NAME, [
                                                    'pos_data' => [
                                                        'reference' => $reference,
                                                        'client_id' => filled($get('client_id')) ? (int) $get('client_id') : null,
                                                        'generate_accounts_receivable' => false,
                                                    ],
                                                    'payment_ves' => $paymentVes,
                                                ]);
                                            })
                                            ->visible(fn (Get $get): bool => $get('payment_method') === 'mixed'),
                                        Select::make('mixed_ves_payment_method')
                                            ->label('Tipo de pago en VES')
                                            ->options([
                                                'punto_venta_ves' => 'Punto de Venta',
                                                'transfer_ves' => 'Transferencia VES',
                                                'pago_movil' => 'Pago Movil',
                                            ])
                                            ->default('punto_venta_ves')
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                                if ($get('payment_method') !== 'mixed') {
                                                    return;
                                                }

                                                $set('bdv_pm_conciliated', false);

                                                if ((string) $state !== 'pago_movil') {
                                                    return;
                                                }

                                                $livewire = $component->getLivewire();
                                                if (! $livewire instanceof HasActions) {
                                                    return;
                                                }

                                                $paymentVes = self::computePaymentBreakdownForForm($get)['payment_ves'];
                                                if ($paymentVes <= 0.00001) {
                                                    return;
                                                }

                                                $reference = trim((string) ($get('reference') ?? ''));
                                                $livewire->mountAction(self::PAGO_MOVIL_CONCILIATION_ACTION_NAME, [
                                                    'pos_data' => [
                                                        'reference' => $reference,
                                                        'client_id' => filled($get('client_id')) ? (int) $get('client_id') : null,
                                                        'generate_accounts_receivable' => false,
                                                    ],
                                                    'payment_ves' => $paymentVes,
                                                ]);
                                            })
                                            ->native(false)
                                            ->visible(fn (Get $get): bool => $get('payment_method') === 'mixed'),
                                        Grid::make([
                                            'default' => 2,
                                        ])
                                            ->schema([
                                                TextEntry::make('payment_usd_preview')
                                                    ->label('Pago en Dolares(US$)')
                                                    ->state(fn (Get $get): string => self::formatMoney(self::computePaymentBreakdownForForm($get)['payment_usd']))
                                                    ->dehydrated(false),
                                                TextEntry::make('payment_ves_preview')
                                                    ->label('Pago en Bolivares(VES)')
                                                    ->state(fn (Get $get): string => self::formatBolivaresReferenceFromVes(self::computePaymentBreakdownForForm($get)['payment_ves']))
                                                    ->dehydrated(false),
                                            ])
                                            ->columnSpanFull(),
                                        TextInput::make('reference')
                                            ->label('Referencia de pago')
                                            ->helperText('Obligatoria para transferencia VES, Zelle y pagos mixtos con parte en bolívares. En Pago Móvil la referencia se indica en la ventana de conciliación BDV.')
                                            ->maxLength(255)
                                            ->markAsRequired(function (Get $get): bool {
                                                $method = (string) ($get('payment_method') ?? '');

                                                if (in_array($method, ['transfer_ves', 'zelle'], true)) {
                                                    return true;
                                                }

                                                if ($method === 'mixed') {
                                                    return self::selectedMixedVesPaymentMethodFromGet($get) === 'transfer_ves'
                                                        && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001;
                                                }

                                                return false;
                                            })
                                            ->rules(fn (Get $get): array => [
                                                Rule::requiredIf(function () use ($get): bool {
                                                    $method = (string) ($get('payment_method') ?? '');

                                                    if (in_array($method, ['transfer_ves', 'zelle'], true)) {
                                                        return true;
                                                    }

                                                    if ($method === 'mixed') {
                                                        return self::selectedMixedVesPaymentMethodFromGet($get) === 'transfer_ves'
                                                            && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001;
                                                    }

                                                    return false;
                                                }),
                                            ])
                                            ->validationMessages([
                                                'required' => 'Debe indicar una referencia de pago para transferencia VES, Zelle o la parte en bolívares del pago mixto.',
                                            ])
                                            ->visible(function (Get $get): bool {
                                                if (in_array($get('payment_method'), ['transfer_ves', 'zelle'], true)) {
                                                    return true;
                                                }

                                                if ($get('payment_method') !== 'mixed') {
                                                    return false;
                                                }

                                                return self::selectedMixedVesPaymentMethodFromGet($get) === 'transfer_ves'
                                                    && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001;
                                            }),
                                    ]),
                            ])
                            ->columnSpan(['lg' => 4]),
                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Action $action) {
                $branchId = Auth::user()?->branch_id;

                if (blank($branchId)) {
                    AuditLogger::record(
                        'pos_caja_sale_blocked',
                        'Caja · No se registró la venta: usuario sin sucursal',
                        properties: ['module' => 'pos_caja', 'reason' => 'usuario_sin_sucursal'],
                    );
                    Notification::make()
                        ->title('Tu usuario no tiene sucursal asignada.')
                        ->danger()
                        ->send();

                    return;
                }

                $paymentMethod = (string) ($data['payment_method'] ?? '');
                $mixedVesPaymentMethod = self::selectedMixedVesPaymentMethodFromData($data);
                $paymentReference = trim((string) ($data['reference'] ?? ''));
                $cardLast4 = trim((string) ($data['card_last4'] ?? ''));

                if (
                    $paymentMethod === 'credito_cliente'
                    && ! filter_var($data['credit_sale_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ) {
                    $livewire = $action->getLivewire();

                    if ($livewire instanceof HasActions) {
                        $livewire->mountAction(self::CREDIT_SALE_CONFIRMATION_ACTION_NAME, [
                            'pos_data' => $data,
                        ]);
                    }

                    return;
                }

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
                    AuditLogger::record(
                        'pos_caja_sale_blocked',
                        'Caja · No se registró la venta: carrito sin líneas válidas',
                        properties: ['module' => 'pos_caja', 'reason' => 'sin_productos'],
                    );
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

                $generateAccountsReceivable = filter_var($data['generate_accounts_receivable'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($paymentMethod === 'credito_cliente') {
                    if (! filled($data['client_id'] ?? null)) {
                        Notification::make()
                            ->title('Cliente obligatorio')
                            ->body('Las ventas con cuenta por cobrar requieren un cliente identificado. Vuelva al paso anterior y seleccione o registre el cliente.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if (! $generateAccountsReceivable) {
                        Notification::make()
                            ->title('Confirme la cuenta por cobrar')
                            ->body('Marque «Generar cuenta por cobrar automáticamente» para registrar la venta a crédito.')
                            ->danger()
                            ->send();

                        return;
                    }
                }

                if ($generateAccountsReceivable && $paymentMethod !== 'credito_cliente') {
                    Notification::make()
                        ->title('Forma de pago incompatible')
                        ->body('Para generar cuenta por cobrar use la opción «Crédito · cuenta por cobrar» en Cobro (o marque el recuadro de CxC).')
                        ->danger()
                        ->send();

                    return;
                }

                $payloadItems = [];

                foreach ($validLines as $i => $entry) {
                    $product = $entry['product'];
                    $qty = $entry['quantity'];
                    $inventory = $entry['inventory'];
                    $productId = (int) $product->id;
                    $unitPricing = self::posUnitPricingForBranch($product, (int) $inventory->branch_id);
                    $unit = $unitPricing['unit_net'];
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

                if (
                    self::usesPagoMovilForVesPortion($paymentMethod, $mixedVesPaymentMethod)
                    && $paymentVes > 0.00001
                ) {
                    $alreadyConciliated = filter_var($data['bdv_pm_conciliated'] ?? false, FILTER_VALIDATE_BOOL);
                    if (! $alreadyConciliated) {
                        AuditLogger::record(
                            'pos_caja_sale_blocked',
                            'Caja · No se registró la venta: Pago Móvil sin validar en BDV',
                            properties: ['module' => 'pos_caja', 'reason' => 'pago_movil_sin_conciliar'],
                        );
                        Notification::make()
                            ->title('Pago no conciliado por BDV')
                            ->body('El pago no fue conciliado por BDV. Complete el asistente de conciliación Pago Móvil y pulse «Validar con BDV» antes de registrar la venta.')
                            ->warning()
                            ->send();
                        $action->halt();

                        return;
                    }
                }

                if (
                    self::usesPointOfSaleForVesPortion($paymentMethod, $mixedVesPaymentMethod)
                    && $paymentVes > 0.00001
                    && ! preg_match('/^\d{4}$/', $cardLast4)
                ) {
                    AuditLogger::record(
                        'pos_caja_sale_blocked',
                        'Caja · No se registró la venta: datos de tarjeta POS incompletos',
                        properties: ['module' => 'pos_caja', 'reason' => 'punto_venta_sin_ultimos_4'],
                    );
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
                } elseif (
                    $paymentMethod === 'mixed'
                    && $mixedVesPaymentMethod === 'punto_venta_ves'
                    && $paymentVes > 0.00001
                ) {
                    $paymentReference = 'MIXTO POS ****'.$cardLast4;
                }

                $shouldRequireReference = in_array($paymentMethod, ['transfer_ves', 'zelle'], true)
                    || (
                        $paymentMethod === 'mixed'
                        && $mixedVesPaymentMethod === 'transfer_ves'
                        && $paymentVes > 0
                    );

                if ($shouldRequireReference && $paymentReference === '') {
                    Notification::make()
                        ->title('Indique la referencia de pago')
                        ->body('La referencia es obligatoria para transferencia VES, Zelle o la parte en bolívares del pago mixto.')
                        ->danger()
                        ->send();

                    return;
                }

                if (
                    self::usesPagoMovilForVesPortion($paymentMethod, $mixedVesPaymentMethod)
                    && $paymentVes > 0.00001
                    && $paymentReference === ''
                ) {
                    Notification::make()
                        ->title('Falta la referencia del Pago Móvil')
                        ->body('Valide el pago en la ventana de conciliación BDV para registrar la referencia antes de cerrar la venta.')
                        ->danger()
                        ->send();

                    return;
                }

                $bcvVesPerUsd = ($vesUsdRate > 0.0 && $paymentVes > 0.00001)
                    ? $vesUsdRate
                    : null;

                $actor = Auth::user()?->email
                    ?? Auth::user()?->name
                    ?? 'sistema';

                try {
                    $sale = DB::transaction(function () use ($branchId, $data, $payloadItems, $lines, $products, $subtotal, $taxTotal, $igtfTotal, $discountTotal, $documentTotal, $actor, $paymentMethod, $paymentUsd, $paymentVes, $paymentReference, $bcvVesPerUsd, $generateAccountsReceivable): Sale {
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
                            'payment_status' => $paymentMethod === 'credito_cliente'
                                ? 'pendiente'
                                : 'paid',
                            'notes' => $generateAccountsReceivable && $paymentMethod === 'credito_cliente'
                                ? 'Venta a crédito · cuenta por cobrar registrada desde caja.'
                                : null,
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

                        if ($paymentMethod === 'credito_cliente' && $generateAccountsReceivable) {
                            AccountsReceivableFromSaleRegistrar::register($sale, $actor);
                        }

                        return $sale;
                    });
                } catch (RuntimeException $e) {
                    AuditLogger::record(
                        'pos_caja_sale_blocked',
                        'Caja · Venta rechazada al guardar',
                        properties: [
                            'module' => 'pos_caja',
                            'reason' => 'error_transaccion',
                            'message' => Str::limit($e->getMessage(), 500),
                        ],
                    );
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                AuditLogger::record(
                    'pos_caja_sale_completed',
                    'Caja · Venta registrada · '.$sale->sale_number,
                    Sale::class,
                    $sale->id,
                    $sale->sale_number,
                    [
                        'module' => 'pos_caja',
                        'payment_method' => $sale->payment_method,
                        'total' => (float) $sale->total,
                        'branch_id' => (int) $sale->branch_id,
                        'client_id' => $sale->client_id,
                        'cuenta_por_cobrar' => $sale->payment_method === 'credito_cliente'
                            && $generateAccountsReceivable,
                    ],
                );

                $saleSuccessBody = 'Total '.self::formatMoney($documentTotal).' · '.$sale->sale_number;
                if ($sale->payment_method === 'credito_cliente') {
                    $saleSuccessBody .= ' · Cuenta por cobrar creada.';
                }

                Notification::make()
                    ->title('Venta registrada')
                    ->body($saleSuccessBody)
                    ->success()
                    ->send();

                $nextUrl = config('fiscal.auto_print_on_sale_complete', true)
                    ? ($sale->payment_method === 'credito_cliente'
                        ? route('sales.delivery-note.print', $sale)
                        : route('sales.fiscal-receipt.print', $sale))
                    : SaleResource::getUrl('view', ['record' => $sale], isAbsolute: false);

                return redirect()->to($nextUrl);
            });
    }

    public static function makeCreditSaleConfirmation(): Action
    {
        return Action::make(self::CREDIT_SALE_CONFIRMATION_ACTION_NAME)
            ->label('Confirmar venta a crédito')
            ->modalHeading('Confirmar venta a crédito')
            ->modalDescription('Está por registrar una venta a crédito. Se generará una cuenta por cobrar y un documento de nota de entrega. ¿Desea continuar?')
            ->modalIcon(Heroicon::ExclamationCircle)
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Sí, registrar venta')
            ->modalCancelActionLabel('No, volver a caja')
            ->modalCancelAction(fn (Action $action): Action => $action->color('gray'))
            ->action(function (Action $action): void {
                $args = $action->getArguments();
                $posData = is_array($args['pos_data'] ?? null) ? $args['pos_data'] : [];
                if ($posData === []) {
                    return;
                }

                $posData['credit_sale_confirmed'] = true;

                $clientIdCxC = isset($posData['client_id']) ? (int) $posData['client_id'] : 0;
                $clientLabel = $clientIdCxC > 0
                    ? Client::query()->whereKey($clientIdCxC)->value('name')
                    : null;
                AuditLogger::record(
                    'pos_caja_credit_confirmed',
                    'Caja · Usuario confirmó venta a crédito / cuenta por cobrar',
                    $clientIdCxC > 0 ? Client::class : null,
                    $clientIdCxC > 0 ? $clientIdCxC : null,
                    filled($clientLabel) ? (string) $clientLabel : null,
                    [
                        'module' => 'pos_caja',
                        'generate_accounts_receivable_expected' => filter_var($posData['generate_accounts_receivable'] ?? false, FILTER_VALIDATE_BOOL),
                    ],
                );

                $livewire = $action->getLivewire();

                if (! $livewire instanceof BasePage) {
                    return;
                }

                self::patchPosRegisterMountedData($livewire, $posData);
                $livewire->replaceMountedAction(self::REGISTER_ACTION_NAME, [
                    'pos_data' => $posData,
                    'client_id' => $posData['client_id'] ?? null,
                ]);
                $livewire->callMountedAction();
            });
    }

    /**
     * Logo BDV para la modal de conciliación Pago Móvil (encabezado Filament).
     */
    private static function bdvPagoMovilModalLogoIconHtml(): HtmlString
    {
        $src = asset('images/logos/bdv-banco-de-venezuela.png');

        return new HtmlString(
            '<img src="'.e($src).'" alt="Banco de Venezuela" class="farmadoc-bdv-pm-modal-icon-img h-14 w-auto max-w-56 object-contain object-left" width="220" height="62" decoding="async" loading="eager" />'
        );
    }

    /**
     * Fecha Y-m-d para la API BDV (DatePicker puede devolver Carbon u otro {@see DateTimeInterface}).
     */
    private static function formatDateForBdvConciliationCandidate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    /**
     * Cédula o RIF en formato alfanumérico compacto para la API BDV (p. ej. V12345678, E12345678, J123456789).
     */
    private static function normalizeBdvCedulaPagadorPayload(string $cedula): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($cedula)) ?? '');
        if ($clean === '') {
            return '';
        }
        if (preg_match('/^[VEJG]\d{5,14}$/', $clean) === 1) {
            return $clean;
        }

        return strlen($clean) <= 32 ? $clean : substr($clean, 0, 32);
    }

    /**
     * A partir del cliente registrado: documento listo para {@see GetMovementRequest} (prefijo V/E/J/G según tipo).
     */
    private static function formatBdvCedulaPagadorFromClient(Client $client): ?string
    {
        $number = trim((string) $client->document_number);
        if ($number === '') {
            return null;
        }

        $type = strtoupper(trim((string) ($client->document_type ?? '')));
        $digitsOnly = preg_replace('/\D/', '', $number) ?? '';
        $alnum = strtoupper(preg_replace('/[^A-Z0-9]/', '', $number) ?? '');

        if ($alnum !== '' && preg_match('/^[VEJG]\d{5,}$/', $alnum) === 1) {
            return self::normalizeBdvCedulaPagadorPayload($alnum);
        }

        $formatted = match ($type) {
            'CE' => $digitsOnly !== '' ? 'E'.$digitsOnly : null,
            'CC' => $digitsOnly !== '' ? 'V'.$digitsOnly : null,
            'RIF' => $alnum !== ''
                ? $alnum
                : ($digitsOnly !== '' ? 'J'.$digitsOnly : null),
            default => $alnum !== '' ? $alnum : null,
        };

        if ($formatted === null || $formatted === '') {
            return null;
        }

        $normalized = self::normalizeBdvCedulaPagadorPayload($formatted);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Teléfono afiliado al Pago Móvil: dígitos y prefijo 04… cuando aplique (58… → 04…).
     */
    private static function normalizeBdvTelefonoPagadorPayload(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '58') && strlen($digits) >= 12) {
            return '0'.substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '4')) {
            return '0'.$digits;
        }

        return strlen($digits) <= 32 ? $digits : substr($digits, 0, 32);
    }

    /**
     * Teléfono del cliente registrado listo para la API BDV.
     */
    private static function formatBdvTelefonoPagadorFromClient(Client $client): ?string
    {
        $normalized = self::normalizeBdvTelefonoPagadorPayload((string) $client->phone);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Entorno getMovement para la caja: opcionalmente {@see config('bdv_conciliation.pos_conciliation_environment')};
     * si no aplica, igual que antes (solo producción Laravel → API producción).
     */
    private static function bdvPosConciliationEnvironment(): string
    {
        $raw = config('bdv_conciliation.pos_conciliation_environment');
        if (is_string($raw) && $raw !== '') {
            $normalized = strtolower(trim($raw));
            if (in_array($normalized, ['qa', 'production'], true)) {
                return $normalized;
            }
        }

        return app()->isProduction() ? 'production' : 'qa';
    }

    /**
     * Importe en formato string esperado por getMovement (punto decimal, sin miles).
     */
    private static function normalizeBdvImporteForPosWizard(mixed $importeField, float $paymentVesFallback): string
    {
        if ($importeField === null || $importeField === '') {
            return number_format(max(0.0, $paymentVesFallback), 2, '.', '');
        }

        if (is_numeric($importeField)) {
            return number_format(max(0.0, (float) $importeField), 2, '.', '');
        }

        $s = trim((string) $importeField);
        if ($s === '') {
            return number_format(max(0.0, $paymentVesFallback), 2, '.', '');
        }

        $s = str_replace(' ', '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',') && ! str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        if (! is_numeric($s)) {
            return $s;
        }

        return number_format(max(0.0, (float) $s), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function redactBdvConciliationPayloadForLog(array $payload): array
    {
        $out = $payload;
        if (isset($out['cedulaPagador']) && is_string($out['cedulaPagador']) && strlen($out['cedulaPagador']) > 4) {
            $out['cedulaPagador'] = '***'.substr($out['cedulaPagador'], -4);
        }
        foreach (['telefonoPagador', 'telefonoDestino'] as $key) {
            if (isset($out[$key]) && is_string($out[$key]) && strlen($out[$key]) > 4) {
                $out[$key] = '***'.substr($out[$key], -4);
            }
        }

        return $out;
    }

    /**
     * Segunda modal (apilada sobre la caja): conciliación BDV para Pago Móvil.
     * Esquema plano (un único fieldset, sin wizard) para que el estado llegue de forma fiable a la acción.
     */
    public static function makePagoMovilConciliation(): Action
    {
        return Action::make(self::PAGO_MOVIL_CONCILIATION_ACTION_NAME)
            ->label('Conciliación Pago Móvil')
            ->modalHeading('Conciliación Pago Móvil')
            ->modalIcon(fn (): HtmlString => self::bdvPagoMovilModalLogoIconHtml())
            ->modalAlignment(Alignment::Center)
            ->extraModalWindowAttributes([
                'class' => 'farmadoc-bdv-pm-conciliation-modal farmadoc-bdv-pm-conciliation-modal--ios-sheet',
            ])
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Validar con BDV')
            ->closeModalByClickingAway(false)
            ->formWrapper(true)
            ->modalCancelAction(function (Action $modalAction): Action {
                return $modalAction
                    ->label('Cerrar')
                    ->color('gray')
                    ->action(function (BasePage $livewire): void {
                        AuditLogger::record(
                            'pos_caja_bdv_modal_abandoned',
                            'Caja · Conciliación BDV cerrada; se cambió a otro método de cobro',
                            properties: [
                                'module' => 'pos_caja',
                                'via' => 'boton_cerrar_modal',
                            ],
                        );
                        $livewire->unmountAction();
                        self::patchPosRegisterMountedData($livewire, [
                            'payment_method' => 'punto_venta_ves',
                            'bdv_pm_conciliated' => false,
                            'generate_accounts_receivable' => false,
                        ]);
                    });
            })
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                // dd('mountUsing', $action, $schema);
                $args = $action->getArguments();
                $posData = is_array($args['pos_data'] ?? null) ? $args['pos_data'] : [];
                $paymentVes = (float) ($args['payment_ves'] ?? 0);
                $ref = trim((string) ($posData['reference'] ?? ''));
                $refDigits = preg_replace('/\D+/', '', $ref) ?? '';
                $prefillReferencia = (preg_match('/^\d{4,6}$/', $refDigits) === 1) ? $refDigits : null;
                $clientId = isset($posData['client_id']) ? (int) $posData['client_id'] : 0;
                $client = $clientId > 0
                    ? Client::query()->find($clientId)
                    : null;
                $prefillCedula = $client instanceof Client
                    ? self::formatBdvCedulaPagadorFromClient($client)
                    : null;
                $prefillTelefono = $client instanceof Client
                    ? self::formatBdvTelefonoPagadorFromClient($client)
                    : null;
                // dd('posData', $posData, 'prefillCedula', $prefillCedula, 'prefillTelefono', $prefillTelefono, 'prefillReferencia', $prefillReferencia);
                $schema?->fill([
                    'bdv_pm_cedula_pagador' => $prefillCedula,
                    'bdv_pm_telefono_pagador' => $prefillTelefono,
                    'bdv_pm_referencia' => $prefillReferencia,
                    'bdv_pm_banco_origen' => VenezuelanPagoMovilBank::BancoDeVenezuela->value,
                    'bdv_pm_fecha_pago' => now()->toDateString(),
                    'bdv_pm_importe' => number_format(max(0.0, $paymentVes), 2, '.', ''),
                    'bdv_pm_req_ced' => '0',
                    'bdv_pm_failed' => false,
                    'bdv_pm_failure_message' => null,
                ]);
            })
            ->schema([
                Fieldset::make('Conciliación Pago Móvil')
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes([
                                'class' => 'farmadoc-bdv-pm-wizard-step-fields',
                            ])
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('bdv_pm_cedula_pagador')
                                    ->label('Cédula / RIF')
                                    ->placeholder('V-12345678')
                                    ->maxLength(32)
                                    ->prefixIcon(Heroicon::Identification)
                                    ->required(),
                                TextInput::make('bdv_pm_telefono_pagador')
                                    ->label('Teléfono afiliado al pago')
                                    ->placeholder('04141234567')
                                    ->maxLength(32)
                                    ->prefixIcon(Heroicon::DevicePhoneMobile)
                                    ->tel()
                                    ->required(),
                            ]),
                        Select::make('bdv_pm_banco_origen')
                            ->label('Banco del pagador')
                            ->helperText('Entidad de origen del Pago Móvil.')
                            ->options(VenezuelanPagoMovilBank::optionsForSelect())
                            ->searchable()
                            ->default(VenezuelanPagoMovilBank::BancoDeVenezuela->value)
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),
                        Grid::make(['default' => 1, 'sm' => 3])
                            ->extraAttributes([
                                'class' => 'farmadoc-bdv-pm-conciliation-detail-row farmadoc-bdv-pm-wizard-step-fields',
                            ])
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('bdv_pm_referencia')
                                    ->label('Referencia')
                                    ->helperText('4–6 dígitos, solo números.')
                                    ->placeholder('123456')
                                    ->inputMode('numeric')
                                    ->minLength(4)
                                    ->maxLength(6)
                                    ->regex('/^\d{4,6}$/')
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->required()
                                    ->validationMessages([
                                        'regex' => 'La referencia debe tener entre 4 y 6 dígitos numéricos.',
                                        'min' => 'La referencia debe tener al menos 4 dígitos.',
                                        'max' => 'La referencia no puede superar 6 dígitos.',
                                    ]),
                                DatePicker::make('bdv_pm_fecha_pago')
                                    ->label('Fecha del pago')
                                    ->helperText('Fecha del comprobante.')
                                    ->default(now())
                                    ->required()
                                    ->prefixIcon(Heroicon::CalendarDays),
                                TextInput::make('bdv_pm_importe')
                                    ->label('Importe (Bs.)')
                                    ->numeric()
                                    ->minValue(0.000001)
                                    ->step(0.000001)
                                    ->prefix('Bs.')
                                    ->prefixIcon(Heroicon::Banknotes)
                                    ->required(),
                            ]),
                        Select::make('bdv_pm_req_ced')
                            ->label('Validar cédula en BDV (reqCed)')
                            ->helperText('«Sí» solo si el manual lo exige para su caso.')
                            ->options([
                                '0' => 'No',
                                '1' => 'Sí',
                            ])
                            ->default('0')
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),
                        Hidden::make('bdv_pm_failed')
                            ->default(false),
                        Hidden::make('bdv_pm_failure_message')
                            ->default(null),
                        TextEntry::make('bdv_pm_failure_inline')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->html()
                            ->hidden(fn (Get $get): bool => ! filter_var($get('bdv_pm_failed') ?? false, FILTER_VALIDATE_BOOL))
                            ->state(fn (Get $get): HtmlString => self::bdvPmFailureInlineHtml($get))
                            ->dehydrated(false)
                            ->extraEntryWrapperAttributes([
                                'class' => 'farmadoc-bdv-pm-inline-failure',
                            ]),
                        Action::make('bdvPmBackToCashRegister')
                            ->label('Regresar a caja y seleccionar otro método')
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->color('danger')
                            ->visible(fn (Get $get): bool => filter_var($get('bdv_pm_failed') ?? false, FILTER_VALIDATE_BOOL))
                            ->extraAttributes([
                                'class' => 'farmadoc-bdv-pm-inline-failure-action',
                            ])
                            ->action(function (Action $action): void {
                                $livewire = $action->getLivewire();
                                if (! $livewire instanceof BasePage) {
                                    return;
                                }

                                AuditLogger::record(
                                    'pos_caja_bdv_modal_abandoned',
                                    'Caja · Conciliación BDV cerrada; se cambió a otro método de cobro',
                                    properties: [
                                        'module' => 'pos_caja',
                                        'via' => 'regresar_a_caja_otro_metodo',
                                    ],
                                );

                                $livewire->unmountAction();
                                self::patchPosRegisterMountedData($livewire, [
                                    'payment_method' => 'punto_venta_ves',
                                    'bdv_pm_conciliated' => false,
                                    'generate_accounts_receivable' => false,
                                ]);
                            }),
                    ]),
            ])
            ->action(function (array $data, Action $action): void {
                $raw = $action->getRawData();
                $rawArray = $raw instanceof Arrayable
                    ? $raw->toArray()
                    : (is_array($raw) ? $raw : []);
                /** @var array<string, mixed> $data */
                $data = array_merge($rawArray, $data);

                $livewire = $action->getLivewire();
                if (! $livewire instanceof BasePage) {
                    return;
                }

                $paymentVes = (float) ($action->getArguments()['payment_ves'] ?? 0);
                $importe = self::normalizeBdvImporteForPosWizard($data['bdv_pm_importe'] ?? null, $paymentVes);

                $telefonoComercio = trim((string) config('bdv_conciliation.commerce_mobile_phone', ''));
                if ($telefonoComercio === '') {
                    Log::warning('bdv.pos_conciliation.config', [
                        'message' => 'Falta TEL (commerce_mobile_phone) para conciliación en caja.',
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        'Falta el teléfono Pago Móvil del comercio (variable TEL en el servidor). No se puede consultar a BDV hasta configurarlo.',
                        'Configuración incompleta',
                    );

                    return;
                }

                $telefonoDestinoNorm = self::normalizeBdvTelefonoPagadorPayload($telefonoComercio);
                if ($telefonoDestinoNorm === '') {
                    Log::warning('bdv.pos_conciliation.config', [
                        'message' => 'TEL presente pero no normalizable a formato BDV (telefonoDestino vacío).',
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        'El teléfono Pago Móvil del comercio (TEL) no tiene un formato válido. Use 04XX… o 58… como indica el manual BDV.',
                        'Configuración incompleta',
                    );

                    return;
                }

                $referencia = preg_replace('/\D+/', '', (string) ($data['bdv_pm_referencia'] ?? '')) ?? '';

                $candidate = [
                    'cedulaPagador' => self::normalizeBdvCedulaPagadorPayload(trim((string) ($data['bdv_pm_cedula_pagador'] ?? ''))),
                    'telefonoPagador' => self::normalizeBdvTelefonoPagadorPayload((string) ($data['bdv_pm_telefono_pagador'] ?? '')),
                    'telefonoDestino' => $telefonoDestinoNorm,
                    'referencia' => $referencia,
                    'fechaPago' => self::formatDateForBdvConciliationCandidate($data['bdv_pm_fecha_pago'] ?? null),
                    'importe' => $importe,
                    'bancoOrigen' => trim((string) ($data['bdv_pm_banco_origen'] ?? '')),
                    'reqCed' => filter_var(($data['bdv_pm_req_ced'] ?? '0') === '1', FILTER_VALIDATE_BOOL),
                ];

                $rules = (new GetMovementRequest)->rules();
                $rules['referencia'] = ['required', 'string', 'regex:/^\d{4,6}$/'];
                $messages = (new GetMovementRequest)->messages();
                $messages['referencia.regex'] = 'La referencia debe tener entre 4 y 6 dígitos numéricos, sin letras ni símbolos.';

                $environment = self::bdvPosConciliationEnvironment();

                try {
                    $validated = validator($candidate, $rules, $messages)->validate();
                    $payload = GetMovementRequest::movementPayloadFromValidated($validated);

                    Log::info('bdv.pos_conciliation.request', [
                        'environment' => $environment,
                        'payload_redacted' => self::redactBdvConciliationPayloadForLog($payload),
                    ]);

                    $response = app(BdvConciliationClient::class)->postGetMovement($payload, $environment);

                    $responseBody = $response->json();
                    $logBody = is_array($responseBody) ? $responseBody : $response->body();

                    if ($response->successful() && self::isBdvConciliationSuccessful($response)) {
                        Log::info('bdv.pos_conciliation.response_ok', [
                            'environment' => $environment,
                            'http_status' => $response->status(),
                            'body' => $logBody,
                        ]);
                    } else {
                        Log::warning('bdv.pos_conciliation.response_not_accepted', [
                            'environment' => $environment,
                            'http_status' => $response->status(),
                            'successful_http' => $response->successful(),
                            'body' => $logBody,
                        ]);
                    }
                } catch (ValidationException $e) {
                    Log::warning('bdv.pos_conciliation.validation_failed', [
                        'environment' => $environment,
                        'errors' => $e->errors(),
                        'payload_redacted' => self::redactBdvConciliationPayloadForLog($candidate),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        collect($e->errors())->flatten()->implode(' '),
                        'Datos inválidos',
                    );

                    return;
                } catch (InvalidArgumentException $e) {
                    Log::error('bdv.pos_conciliation.invalid_argument', [
                        'environment' => $environment,
                        'message' => $e->getMessage(),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        self::truncateForUserMessage($e->getMessage()),
                        'Conciliación no aceptada',
                    );

                    return;
                } catch (ConnectionException $e) {
                    Log::error('bdv.pos_conciliation.connection', [
                        'environment' => $environment,
                        'message' => $e->getMessage(),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        'No hubo respuesta del banco: '.self::truncateForUserMessage($e->getMessage()).' Compruebe la red o reintente más tarde.',
                        'Sin conexión con BDV',
                    );

                    return;
                } catch (RuntimeException $e) {
                    Log::error('bdv.pos_conciliation.runtime', [
                        'environment' => $environment,
                        'message' => $e->getMessage(),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        self::truncateForUserMessage($e->getMessage()),
                        'Conciliación no aceptada',
                    );

                    return;
                } catch (Throwable $e) {
                    Log::error('bdv.pos_conciliation.exception', [
                        'environment' => $environment,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        'Ocurrió un error al conciliar: '.self::truncateForUserMessage($e->getMessage()),
                        'Conciliación fallida',
                    );

                    return;
                }

                if (! self::isBdvConciliationSuccessful($response)) {
                    self::notifyBdvConciliationApiFailure($response);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        self::bdvConciliationFailureMessage($response),
                        'Pago no conciliado por BDV',
                    );

                    return;
                }

                AuditLogger::record(
                    'pos_caja_bdv_conciliation_ok',
                    'Caja · Pago Móvil validado por BDV',
                    properties: [
                        'module' => 'pos_caja',
                        'environment' => $environment,
                        'referencia_suffix' => strlen($candidate['referencia']) >= 2
                            ? substr($candidate['referencia'], -2)
                            : null,
                        'importe_bs' => $candidate['importe'],
                    ],
                );

                self::patchPosRegisterMountedData($livewire, [
                    'reference' => $candidate['referencia'],
                    'bdv_pm_conciliated' => true,
                ]);

                $livewire->js(self::bdvPmConciliationSuccessOverlayScript());
            });
    }

    /**
     * Guarda el fallo de conciliación en el wizard para mostrar alerta inline y acción de retorno a caja.
     */
    private static function markBdvConciliationFailureInWizard(
        BasePage $livewire,
        string $detailMessage,
        ?string $title = null,
    ): void {
        $message = trim($detailMessage);
        if ($title !== null && $title !== '') {
            $message = $title.'. '.$message;
        }

        self::patchMountedActionData($livewire, self::PAGO_MOVIL_CONCILIATION_ACTION_NAME, [
            'bdv_pm_failed' => true,
            'bdv_pm_failure_message' => $message,
        ]);

        AuditLogger::record(
            'pos_caja_bdv_conciliation_failed',
            'Caja · Conciliación Pago Móvil no aceptada',
            properties: [
                'module' => 'pos_caja',
                'detail' => Str::limit($message, 800),
            ],
        );
    }

    private static function truncateForUserMessage(string $message, int $maxLength = 280): string
    {
        $t = trim($message);

        if ($t === '') {
            return '';
        }

        if (strlen($t) <= $maxLength) {
            return $t;
        }

        return substr($t, 0, $maxLength - 1).'…';
    }

    private static function bdvPmFailureInlineHtml(Get $get): HtmlString
    {
        $message = trim((string) ($get('bdv_pm_failure_message') ?? ''));
        if ($message === '') {
            $message = 'El banco no confirmó el pago.';
        }

        return new HtmlString(
            '<div class="farmadoc-bdv-pm-inline-alert" role="alert" aria-live="assertive">'
            .'<p class="farmadoc-bdv-pm-inline-alert__title">Pago no conciliado por BDV</p>'
            .'<p class="farmadoc-bdv-pm-inline-alert__body">'.e($message).'</p>'
            .'<p class="farmadoc-bdv-pm-inline-alert__hint">Use el botón de abajo para volver a la caja y escoger otro método de pago.</p>'
            .'</div>'
        );
    }

    /**
     * Muestra overlay con check animado, espera 2 s, cierra la conciliación y envía «Registrar venta».
     */
    private static function bdvPmConciliationSuccessOverlayScript(): string
    {
        return <<<'JS'
(function () {
    const root = document.querySelector('.farmadoc-bdv-pm-conciliation-modal--ios-sheet');
    if (!root) {
        $wire.unmountAction();
        setTimeout(() => $wire.callMountedAction(), 150);

        return;
    }

    const overlay = document.createElement('div');
    overlay.className = 'farmadoc-bdv-pm-success-overlay';
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML = '<div class="farmadoc-bdv-pm-success-overlay__backdrop"></div>'
        + '<div class="farmadoc-bdv-pm-success-overlay__card">'
        + '<div class="farmadoc-bdv-pm-success-check" aria-hidden="true">'
        + '<svg class="farmadoc-bdv-pm-success-check__svg" viewBox="0 0 52 52" width="68" height="68" focusable="false">'
        + '<circle class="farmadoc-bdv-pm-success-check__circle" cx="26" cy="26" r="23" fill="none" stroke="currentColor" stroke-width="2.5"/>'
        + '<path class="farmadoc-bdv-pm-success-check__tick" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" d="M14 27l8 8 16-20"/>'
        + '</svg></div>'
        + '<p class="farmadoc-bdv-pm-success-overlay__title">Pago conciliado</p>'
        + '<p class="farmadoc-bdv-pm-success-overlay__sub">Registrando la venta…</p></div>';

    root.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('farmadoc-bdv-pm-success-overlay--visible'));

    setTimeout(() => {
        overlay.classList.add('farmadoc-bdv-pm-success-overlay--exiting');
    }, 1700);

    setTimeout(() => {
        $wire.unmountAction();
    }, 2000);

    setTimeout(() => {
        $wire.callMountedAction();
    }, 2150);
})();
JS;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private static function patchPosRegisterMountedData(BasePage $livewire, array $patch): void
    {
        self::patchMountedActionData($livewire, self::REGISTER_ACTION_NAME, $patch);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private static function patchMountedActionData(BasePage $livewire, string $actionName, array $patch): void
    {
        $mounted = $livewire->mountedActions ?? null;
        if (! is_array($mounted) || $mounted === []) {
            return;
        }

        foreach ($mounted as $i => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['name'] ?? '') !== $actionName) {
                continue;
            }

            $current = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            $mounted[$i]['data'] = array_merge($current, $patch);
            $livewire->mountedActions = $mounted;

            return;
        }
    }

    private static function isBdvConciliationSuccessful(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return true;
        }

        if (isset($decoded['status']) && is_numeric($decoded['status']) && (int) $decoded['status'] >= 400) {
            return false;
        }

        if (isset($decoded['codigo'])) {
            return in_array((string) $decoded['codigo'], ['00', '01'], true);
        }

        if (! isset($decoded['code'])) {
            return true;
        }

        $code = $decoded['code'];
        if (in_array($code, ['1000', '200', '00'], true)) {
            return true;
        }

        $asInt = is_int($code) ? $code : (is_numeric($code) ? (int) $code : null);
        if ($asInt !== null && in_array($asInt, [1000, 200], true)) {
            return true;
        }

        return false;
    }

    private static function bdvConciliationFailureMessage(Response $response): string
    {
        $decoded = $response->json();
        if (is_array($decoded)) {
            foreach (['message', 'error', 'descripcion', 'detalle'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key]) && trim($decoded[$key]) !== '') {
                    return trim($decoded[$key]);
                }
            }

            if (isset($decoded['code']) || isset($decoded['codigo'])) {
                $code = (string) ($decoded['code'] ?? $decoded['codigo']);

                return 'BDV devolvió código '.$code.' en la conciliación.';
            }
        }

        return 'El banco no confirmó la conciliación del Pago Móvil (HTTP '.$response->status().').';
    }

    /**
     * Notificación con el detalle devuelto por BDV cuando la respuesta no es una conciliación satisfactoria
     * (p. ej. distinta de code 1000 / data.status 1000 y HTTP 200 según el contrato documentado).
     */
    private static function notifyBdvConciliationApiFailure(Response $response): void
    {
        $body = self::truncateForUserMessage(self::formatBdvApiResponseForNotification($response), 1800);

        Notification::make()
            ->title('BDV: no se concilió el Pago Móvil')
            ->body($body !== '' ? $body : 'El banco no confirmó el pago. Revise los datos o intente de nuevo.')
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Resume la respuesta HTTP/JSON de getMovement para mostrarla al usuario.
     */
    private static function formatBdvApiResponseForNotification(Response $response): string
    {
        $lines = ['HTTP '.$response->status()];
        $decoded = $response->json();

        if (! is_array($decoded)) {
            $raw = trim($response->body());
            if ($raw !== '') {
                $lines[] = 'Cuerpo: '.$raw;
            }

            return implode("\n", $lines);
        }

        foreach (['code', 'codigo'] as $codeKey) {
            if (array_key_exists($codeKey, $decoded)) {
                $val = $decoded[$codeKey];
                $lines[] = 'Código: '.(is_scalar($val) ? (string) $val : json_encode($val));

                break;
            }
        }

        if (isset($decoded['message']) && is_string($decoded['message']) && trim($decoded['message']) !== '') {
            $lines[] = 'Mensaje: '.trim($decoded['message']);
        }

        foreach (['error', 'errors', 'descripcion', 'detalle'] as $key) {
            if (! isset($decoded[$key])) {
                continue;
            }
            $fragment = is_string($decoded[$key])
                ? trim($decoded[$key])
                : (json_encode($decoded[$key], JSON_UNESCAPED_UNICODE) ?: '');
            if ($fragment !== '' && $fragment !== '[]' && $fragment !== '{}') {
                $lines[] = ucfirst($key).': '.$fragment;
            }
        }

        $data = $decoded['data'] ?? null;
        if (is_array($data)) {
            foreach (['status', 'amount', 'reason', 'codigo', 'mensaje'] as $dk) {
                if (! array_key_exists($dk, $data)) {
                    continue;
                }
                $v = $data[$dk];
                $lines[] = 'data.'.$dk.': '.(is_scalar($v) ? (string) $v : (json_encode($v, JSON_UNESCAPED_UNICODE) ?: ''));
            }
        }

        if (isset($decoded['status']) && ! is_array($decoded['status'])) {
            $lines[] = 'status (raíz): '.(is_scalar($decoded['status']) ? (string) $decoded['status'] : (json_encode($decoded['status'], JSON_UNESCAPED_UNICODE) ?: ''));
        }

        return implode("\n", $lines);
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
     * Referencia en bolívares para el precio de lista (USD): si el producto grava IVA, el monto en Bs. incluye el IVA sobre la base convertida.
     */
    private static function posListPriceVesFromUsd(float $usdUnit, bool $appliesVat, float $vesPerUsd): float
    {
        if ($vesPerUsd <= 0.0) {
            return 0.0;
        }

        $baseVes = round($usdUnit * $vesPerUsd, 2);
        if (! $appliesVat) {
            return $baseVes;
        }

        $vatRate = DefaultVatRate::percent();
        if ($vatRate <= 0.0) {
            return $baseVes;
        }

        return round($baseVes + round($baseVes * $vatRate / 100, 2), 2);
    }

    /**
     * Etiqueta de opción en el buscador POS sin cargar modelos (solo datos ya resueltos en SQL + tasa precalculada).
     */
    private static function formatPosSearchOptionLabelFast(string $base, float $saleUsd, bool $appliesVat, float $branchQty, float $rate): string
    {
        $label = $base.' · '.self::formatMoney($saleUsd);
        if ($rate > 0.0) {
            $label .= ' · '.self::formatBolivaresReferenceFromVes(self::posListPriceVesFromUsd($saleUsd, $appliesVat, $rate));
        } else {
            $label .= ' · Bs. —';
        }
        if ($branchQty <= 0.0001) {
            $label .= ' · Cant. 0';
        } else {
            $label .= ' · Cant. '.number_format($branchQty, 2, ',', '.');
        }

        return $label;
    }

    /**
     * Etiqueta para validación del Select cuando el valor no está en el último resultado de búsqueda (2 lecturas ligeras).
     */
    private static function buildPosSearchOptionLabelFromCatalog(int $branchId, int $productId, ?Get $get): ?string
    {
        $row = DB::table('products')
            ->select(['name', 'barcode'])
            ->where('id', $productId)
            ->where('is_active', true)
            ->first();
        if ($row === null) {
            return null;
        }

        self::warmPosDataForBranch($branchId, [$productId]);
        $product = self::posProduct($productId);

        $base = filled($row->barcode)
            ? $row->barcode.' · '.$row->name
            : $row->name;
        $qty = (float) (DB::table('inventories')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->value('quantity') ?? 0);
        $rate = self::effectiveVesUsdRateForPosProductLabels($get);
        $unitPricing = $product instanceof Product
            ? self::posUnitPricingForBranch($product, $branchId)
            : [
                'unit_net' => 0.0,
                'unit_final' => 0.0,
                'applies_vat' => false,
            ];

        return self::formatPosSearchOptionLabelFast(
            $base,
            $unitPricing['unit_net'],
            $unitPricing['applies_vat'],
            max(0.0, $qty),
            $rate,
        );
    }

    /**
     * Tasa Bs./USD para etiquetas de producto en la caja: primero el formulario (API BCV o manual), si no, la del día vía {@see initialDolarFormState()}.
     */
    private static function effectiveVesUsdRateForPosProductLabels(?Get $get): float
    {
        if ($get !== null) {
            $fromForm = self::effectiveVesUsdRate($get);
            if ($fromForm > 0.0) {
                return $fromForm;
            }
        }

        $api = self::officialVesUsdRateFromInitialFormStateCached();
        if ($api > 0.0) {
            return $api;
        }

        return 0.0;
    }

    /**
     * Tasa oficial cacheada por petición (evita repetir llamadas a la API del dólar en cada fila del buscador).
     */
    private static function officialVesUsdRateFromInitialFormStateCached(): float
    {
        if (request()->attributes->has('cash_register.official_ves_usd_rate')) {
            return (float) request()->attributes->get('cash_register.official_ves_usd_rate');
        }

        $initial = self::initialDolarFormState();
        $api = $initial['ves_usd_rate'] ?? null;
        $rate = (is_numeric($api) && (float) $api > 0) ? (float) $api : 0.0;
        request()->attributes->set('cash_register.official_ves_usd_rate', $rate);

        return $rate;
    }

    /**
     * Sufijo para el título de línea POS: precio en Bs. (tasa BCV/formulario) y existencia en la sucursal del usuario.
     */
    private static function posProductBolivaresAndBranchStockSuffix(int $branchId, Product $product, Get $get): string
    {
        self::warmPosDataForBranch($branchId, [(int) $product->id]);

        $unitPricing = self::posUnitPricingForBranch($product, $branchId);
        $usd = $unitPricing['unit_net'];
        $segments = [];
        $rate = self::effectiveVesUsdRateForPosProductLabels($get);
        if ($rate > 0.0) {
            $segments[] = self::formatBolivaresReferenceFromVes(
                self::posListPriceVesFromUsd($usd, $unitPricing['applies_vat'], $rate),
            );
        }

        $inv = self::posBranchInventory($branchId, (int) $product->id);
        $qty = $inv instanceof Inventory ? max(0.0, (float) $inv->quantity) : 0.0;
        if ($qty <= 0.0001) {
            $segments[] = 'Cant. 0';
        } else {
            $segments[] = 'Cant. '.number_format($qty, 2, ',', '.');
        }

        return $segments === [] ? '' : (' · '.implode(' · ', $segments));
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function resolvePaymentAmounts(float $documentTotalUsd, string $paymentMethod, float $mixedUsdPaid = 0, float $vesUsdRate = 0): array
    {
        $rate = max(0.0, $vesUsdRate);

        return match ($paymentMethod) {
            'credito_cliente' => [0.0, 0.0],
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
            'transfer_ves', 'pago_movil', 'efectivo_ves', 'punto_venta_ves', 'mixed', 'credito_cliente' => false,
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
        $linePricing = [];

        foreach ($lines as $index => $line) {
            $product = $line['product'];
            $qty = $line['quantity'];
            $inventory = $line['inventory'];
            $branchId = $inventory instanceof Inventory ? (int) $inventory->branch_id : 0;
            $linePricing[$index] = self::posUnitPricingForBranch($product, $branchId);
            $lineGross[] = round($qty * $linePricing[$index]['unit_net'], 2);
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
            $lineNet = $lineNets[$i] ?? 0.0;
            $appliesVat = (bool) ($linePricing[$i]['applies_vat'] ?? false);
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

    private static function selectedMixedVesPaymentMethodFromGet(Get $get): string
    {
        $method = (string) ($get('mixed_ves_payment_method') ?? 'punto_venta_ves');

        return in_array($method, ['transfer_ves', 'pago_movil', 'punto_venta_ves'], true)
            ? $method
            : 'punto_venta_ves';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function selectedMixedVesPaymentMethodFromData(array $data): string
    {
        $method = (string) ($data['mixed_ves_payment_method'] ?? 'punto_venta_ves');

        return in_array($method, ['transfer_ves', 'pago_movil', 'punto_venta_ves'], true)
            ? $method
            : 'punto_venta_ves';
    }

    private static function usesPagoMovilForVesPortion(string $paymentMethod, string $mixedVesPaymentMethod): bool
    {
        return $paymentMethod === 'pago_movil'
            || ($paymentMethod === 'mixed' && $mixedVesPaymentMethod === 'pago_movil');
    }

    private static function usesPointOfSaleForVesPortion(string $paymentMethod, string $mixedVesPaymentMethod): bool
    {
        return $paymentMethod === 'punto_venta_ves'
            || ($paymentMethod === 'mixed' && $mixedVesPaymentMethod === 'punto_venta_ves');
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
     * Total de una línea desde el estado del ítem del repeater (precio lista × cantidad; con IVA si el producto grava).
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

        $unitPricing = self::posUnitPricingForBranch($product, (int) $branchId);

        return round($qty * $unitPricing['unit_final'], 2);
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
                'product_category_id',
            ];
            if (SchemaFacade::hasColumn('products', 'express_branch_prices')) {
                $select[] = 'express_branch_prices';
            }
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

    private static function posAvailableQuantity(int $branchId, int $productId): ?float
    {
        $inventory = self::posBranchInventory($branchId, $productId)
            ?? self::ensurePosBranchInventoryRecord($branchId, $productId);

        if (! $inventory instanceof Inventory) {
            return null;
        }

        return round((float) $inventory->quantity - (float) $inventory->reserved_quantity, 3);
    }

    private static function notifyPosStockZero(string $productLabel): void
    {
        Notification::make()
            ->title('Producto sin existencia')
            ->body('El producto "'.$productLabel.'" tiene existencia 0. Escanee otro producto para continuar.')
            ->warning()
            ->send();
    }

    private static function notifyPosQuantityExceedsStock(string $productLabel, float $requestedQuantity, float $availableQuantity): void
    {
        Notification::make()
            ->title('Cantidad mayor a la existencia')
            ->body(
                'Para "'.$productLabel.'" solicitó '.number_format($requestedQuantity, 3, '.', '').
                ' y solo hay '.number_format($availableQuantity, 3, '.', '').' disponibles.'
            )
            ->warning()
            ->send();
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
    private static function searchInventoryProductsForBranch(int $branchId, string $search, ?Get $get = null, bool $requirePositiveQuantity = false): array
    {
        $term = trim($search);

        if ($term !== '') {
            $exactProductId = DB::table('inventories')
                ->join('products', 'products.id', '=', 'inventories.product_id')
                ->where('inventories.branch_id', $branchId)
                ->where('products.is_active', true)
                ->whereNotNull('inventories.product_id')
                ->when($requirePositiveQuantity, fn ($q) => $q->where('inventories.quantity', '>', 0))
                ->where('products.barcode', $term)
                ->value('products.id');

            if (blank($exactProductId) && SchemaFacade::hasColumn('products', 'sku')) {
                $exactProductId = DB::table('inventories')
                    ->join('products', 'products.id', '=', 'inventories.product_id')
                    ->where('inventories.branch_id', $branchId)
                    ->where('products.is_active', true)
                    ->whereNotNull('inventories.product_id')
                    ->when($requirePositiveQuantity, fn ($q) => $q->where('inventories.quantity', '>', 0))
                    ->where('products.sku', $term)
                    ->value('products.id');
            }

            if (blank($exactProductId) && SchemaFacade::hasColumn('products', 'slug')) {
                $exactProductId = DB::table('inventories')
                    ->join('products', 'products.id', '=', 'inventories.product_id')
                    ->where('inventories.branch_id', $branchId)
                    ->where('products.is_active', true)
                    ->whereNotNull('inventories.product_id')
                    ->when($requirePositiveQuantity, fn ($q) => $q->where('inventories.quantity', '>', 0))
                    ->where('products.slug', $term)
                    ->value('products.id');
            }

            // Misma lógica que la compra: producto activo en catálogo aunque aún no tenga fila en inventarios de esta sucursal.
            if (blank($exactProductId)) {
                $catalogId = self::resolvePosProductIdByExactCatalogCode($term);
                if ($catalogId !== null) {
                    if ($requirePositiveQuantity) {
                        $catQty = (float) (DB::table('inventories')
                            ->where('branch_id', $branchId)
                            ->where('product_id', $catalogId)
                            ->value('quantity') ?? 0);
                        if ($catQty > 0.0001) {
                            $exactProductId = $catalogId;
                        }
                    } else {
                        $exactProductId = $catalogId;
                    }
                }
            }

            if (filled($exactProductId)) {
                $id = (int) $exactProductId;
                $selectCols = ['id', 'name', 'barcode', 'sale_price', 'applies_vat'];
                if (SchemaFacade::hasColumn('products', 'sku')) {
                    $selectCols[] = 'sku';
                }

                $row = DB::table('products')
                    ->select($selectCols)
                    ->where('id', $id)
                    ->where('is_active', true)
                    ->first();

                if ($row !== null) {
                    self::warmPosDataForBranch($branchId, [$id]);
                    $product = self::posProduct($id);
                    $base = filled($row->barcode)
                        ? $row->barcode.' · '.$row->name
                        : $row->name;
                    $qty = (float) (DB::table('inventories')
                        ->where('branch_id', $branchId)
                        ->where('product_id', $id)
                        ->value('quantity') ?? 0);
                    $rate = self::effectiveVesUsdRateForPosProductLabels($get);
                    $unitPricing = $product instanceof Product
                        ? self::posUnitPricingForBranch($product, $branchId)
                        : [
                            'unit_net' => (float) ($row->sale_price ?? 0),
                            'unit_final' => 0.0,
                            'applies_vat' => (bool) ($row->applies_vat ?? false),
                        ];

                    if ($requirePositiveQuantity && max(0.0, $qty) <= 0.0001) {
                        return [];
                    }

                    return [$id => self::formatPosSearchOptionLabelFast(
                        $base,
                        $unitPricing['unit_net'],
                        $unitPricing['applies_vat'],
                        max(0.0, $qty),
                        $rate,
                    )];
                }
            }
        }

        $selectList = [
            'products.id',
            'products.name',
            'products.barcode',
            'products.sale_price',
            'products.applies_vat',
            'inventories.quantity as branch_quantity',
        ];
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
            ->when($requirePositiveQuantity, fn ($q) => $q->where('inventories.quantity', '>', 0))
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

        self::warmPosDataForBranch(
            $branchId,
            $rows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );

        $rate = self::effectiveVesUsdRateForPosProductLabels($get);

        return $rows->mapWithKeys(function ($row) use ($rate, $branchId): array {
            $id = (int) $row->id;
            $product = self::posProduct($id);
            $base = filled($row->barcode)
                ? $row->barcode.' · '.$row->name
                : $row->name;
            $qty = isset($row->branch_quantity) ? max(0.0, (float) $row->branch_quantity) : 0.0;
            $unitPricing = $product instanceof Product
                ? self::posUnitPricingForBranch($product, $branchId)
                : [
                    'unit_net' => (float) ($row->sale_price ?? 0),
                    'unit_final' => 0.0,
                    'applies_vat' => (bool) ($row->applies_vat ?? false),
                ];

            return [$id => self::formatPosSearchOptionLabelFast(
                $base,
                $unitPricing['unit_net'],
                $unitPricing['applies_vat'],
                $qty,
                $rate,
            )];
        })->all();
    }

    /**
     * @return array{unit_net: float, unit_final: float, applies_vat: bool}
     */
    private static function posUnitPricingForBranch(Product $product, int $branchId): array
    {
        $baseUnitNet = round(max(0.0, (float) ($product->sale_price ?? 0)), 2);
        $appliesVat = (bool) ($product->applies_vat ?? false);
        $vatRate = max(0.0, DefaultVatRate::percent());
        $baseUnitFinal = $appliesVat && $vatRate > 0.0
            ? round($baseUnitNet + round($baseUnitNet * $vatRate / 100, 2), 2)
            : $baseUnitNet;

        if ($branchId <= 0 || self::isImportedCategoryProduct($product)) {
            return [
                'unit_net' => $baseUnitNet,
                'unit_final' => $baseUnitFinal,
                'applies_vat' => $appliesVat,
            ];
        }

        $expressProfit = self::posBranchExpressProfitPercentage($branchId);
        if ($expressProfit === null) {
            return [
                'unit_net' => $baseUnitNet,
                'unit_final' => $baseUnitFinal,
                'applies_vat' => $appliesVat,
            ];
        }

        $expressData = self::expressPriceDataForBranch($product, $branchId);
        $expressWithoutVat = $expressData['final_price_without_vat'] ?? null;
        $expressWithVat = $expressData['final_price_with_vat'] ?? null;

        if ($expressWithoutVat === null) {
            $costPrice = max(0.0, (float) ($product->cost_price ?? 0));
            $expressWithoutVat = round($costPrice + ($costPrice * $expressProfit / 100), 2);
        }

        if ($appliesVat) {
            if ($expressWithVat === null) {
                $expressWithVat = $vatRate > 0.0
                    ? round($expressWithoutVat + round($expressWithoutVat * $vatRate / 100, 2), 2)
                    : $expressWithoutVat;
            }

            return [
                'unit_net' => round(max(0.0, $expressWithoutVat), 2),
                'unit_final' => round(max(0.0, $expressWithVat), 2),
                'applies_vat' => true,
            ];
        }

        $withoutVatForNoVatProduct = $expressWithoutVat > 0.0
            ? $expressWithoutVat
            : ($expressWithVat ?? 0.0);

        return [
            'unit_net' => round(max(0.0, $withoutVatForNoVatProduct), 2),
            'unit_final' => round(max(0.0, $withoutVatForNoVatProduct), 2),
            'applies_vat' => false,
        ];
    }

    /**
     * @return array{final_price_without_vat: float, final_price_with_vat: ?float}|null
     */
    private static function expressPriceDataForBranch(Product $product, int $branchId): ?array
    {
        $raw = $product->express_branch_prices;
        if (! is_array($raw)) {
            return null;
        }

        $entry = $raw[(string) $branchId] ?? $raw[$branchId] ?? null;
        if (! is_array($entry)) {
            return null;
        }

        $withoutVatRaw = $entry['final_price_without_vat'] ?? null;
        $withVatRaw = $entry['final_price_with_vat'] ?? null;
        $withoutVat = is_numeric($withoutVatRaw) ? max(0.0, (float) $withoutVatRaw) : null;
        $withVat = is_numeric($withVatRaw) ? max(0.0, (float) $withVatRaw) : null;

        if ($withoutVat === null && $withVat === null) {
            return null;
        }

        if ($withoutVat === null && $withVat !== null) {
            $withoutVat = $withVat;
        }

        return [
            'final_price_without_vat' => round(max(0.0, (float) $withoutVat), 2),
            'final_price_with_vat' => $withVat !== null ? round($withVat, 2) : null,
        ];
    }

    private static function posBranchExpressProfitPercentage(int $branchId): ?float
    {
        if ($branchId <= 0) {
            return null;
        }

        /** @var array<int, float|null> $cache */
        $cache = request()->attributes->get('cash_register.express_profit_by_branch', []);
        if (! is_array($cache)) {
            $cache = [];
        }

        if (array_key_exists($branchId, $cache)) {
            return $cache[$branchId];
        }

        $profit = FarmaExpressCostStructure::query()
            ->where('branch_id', $branchId)
            ->value('profit_percentage');

        $cache[$branchId] = is_numeric($profit)
            ? max(0.0, (float) $profit)
            : null;

        request()->attributes->set('cash_register.express_profit_by_branch', $cache);

        return $cache[$branchId];
    }

    private static function isImportedCategoryProduct(Product $product): bool
    {
        $categoryId = (int) ($product->product_category_id ?? 0);
        if ($categoryId <= 0) {
            return false;
        }

        $name = self::productCategoryNameForPos($categoryId);
        if ($name === null) {
            return false;
        }

        $normalized = mb_strtoupper(Str::ascii(trim($name)));

        return $normalized === 'IMPORTADOS';
    }

    private static function productCategoryNameForPos(int $categoryId): ?string
    {
        /** @var array<int, string|null> $cache */
        $cache = request()->attributes->get('cash_register.product_category_name_by_id', []);
        if (! is_array($cache)) {
            $cache = [];
        }

        if (array_key_exists($categoryId, $cache)) {
            return $cache[$categoryId];
        }

        $name = ProductCategory::query()
            ->whereKey($categoryId)
            ->value('name');

        $cache[$categoryId] = is_string($name) && trim($name) !== ''
            ? trim($name)
            : null;

        request()->attributes->set('cash_register.product_category_name_by_id', $cache);

        return $cache[$categoryId];
    }

    private static function appendProductToPosLineItems(int $branchId, int $productId, Set $set, Get $get): void
    {
        self::warmPosDataForBranch($branchId, [$productId]);

        $product = self::posProduct($productId);
        $productLabel = $product instanceof Product
            ? $product->name
            : 'Producto #'.$productId;

        $available = self::posAvailableQuantity($branchId, $productId);
        if ($available !== null && $available <= 0.0001) {
            self::notifyPosStockZero($productLabel);

            return;
        }

        $lineItems = $get('line_items');
        if (! is_array($lineItems)) {
            $lineItems = [];
        }

        foreach ($lineItems as $key => $row) {
            if (! is_array($row) || ! filled($row['product_id'] ?? null)) {
                continue;
            }

            if ((int) $row['product_id'] !== $productId) {
                continue;
            }

            $currentQuantity = max(0.001, (float) ($row['quantity'] ?? 1));
            $nextQuantity = round($currentQuantity + 1, 3);
            if ($available !== null && $nextQuantity > ($available + 0.0001)) {
                self::notifyPosQuantityExceedsStock($productLabel, $nextQuantity, $available);

                return;
            }

            $set("line_items.{$key}.quantity", $nextQuantity);

            return;
        }

        $targetKey = null;
        foreach ($lineItems as $key => $row) {
            if (is_array($row) && blank($row['product_id'] ?? null)) {
                $targetKey = $key;
                break;
            }
        }

        if ($targetKey === null) {
            $targetKey = (string) Str::uuid();
            $set("line_items.{$targetKey}", [
                'product_id' => null,
                'quantity' => 1,
            ]);
            $lineItems[$targetKey] = [
                'product_id' => null,
                'quantity' => 1,
            ];
        }

        $set("line_items.{$targetKey}.product_id", $productId);
        $set("line_items.{$targetKey}.quantity", 1);

        $keys = array_keys($lineItems);
        $lastKey = $keys === [] ? null : $keys[array_key_last($keys)];
        if ($lastKey !== null && (string) $targetKey === (string) $lastKey) {
            $set('line_items.'.Str::uuid(), [
                'product_id' => null,
                'quantity' => 1,
            ]);
        }
    }

    private static function ensurePosTrailingEmptyLine(Set $set, Get $get): void
    {
        $lineItems = $get('line_items');
        if (! is_array($lineItems) || $lineItems === []) {
            $set('line_items', [[
                'product_id' => null,
                'quantity' => 1,
            ]]);

            return;
        }

        $hasEmpty = false;
        foreach ($lineItems as $row) {
            if (is_array($row) && blank($row['product_id'] ?? null)) {
                $hasEmpty = true;
                break;
            }
        }

        if (! $hasEmpty) {
            $set('line_items.'.Str::uuid(), [
                'product_id' => null,
                'quantity' => 1,
            ]);
        }
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
            'pos_sale_transfer_id' => null,
            'pos_product_search' => null,
            'payment_method' => 'punto_venta_ves',
            'mixed_ves_payment_method' => 'punto_venta_ves',
            'generate_accounts_receivable' => false,
            'bdv_pm_conciliated' => false,
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
     * @return array<int|string, string>
     */
    private static function posSaleTransferSearchResults(string $search): array
    {
        $query = self::posSaleTransferBaseQuery();
        $term = trim($search);
        // Normalizar guiones tipográficos al pegar desde WhatsApp u otros (PHP requiere comillas dobles para \u{…}).
        $term = str_replace(
            ["\u{2013}", "\u{2014}", "\u{2212}"],
            ['-', '-', '-'],
            $term,
        );
        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $digitsOnly = preg_replace('/\D+/', '', $term) ?? '';

            $query->where(function (Builder $q) use ($like, $term, $digitsOnly): void {
                $q->where('code', 'like', $like);

                if ($digitsOnly !== '' && strlen($digitsOnly) >= 3) {
                    $q->orWhere('code', 'like', '%'.$digitsOnly.'%');
                }

                if (ctype_digit($term)) {
                    $q->orWhereKey((int) $term);
                }

                $q->orWhereHas('fromBranch', function (Builder $b) use ($like): void {
                    $b->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                })->orWhereHas('toBranch', function (Builder $b) use ($like): void {
                    $b->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            });
        }

        return $query
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (ProductTransfer $transfer): array => [
                $transfer->id => self::posSaleTransferLabel($transfer),
            ])
            ->all();
    }

    private static function posSaleTransferOptionLabel(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $transfer = self::posSaleTransferBaseQuery()
            ->whereKey((int) $value)
            ->first();

        if (! $transfer instanceof ProductTransfer) {
            return null;
        }

        return self::posSaleTransferLabel($transfer);
    }

    /**
     * @return Builder<ProductTransfer>
     */
    private static function posSaleTransferBaseQuery(): Builder
    {
        $query = ProductTransfer::query()
            ->with([
                'fromBranch:id,name',
                'toBranch:id,name',
                'items:id,product_transfer_id,product_id,quantity',
            ])
            ->where('transfer_type', 'sale_transfer')
            ->where('status', ProductTransferStatus::InProgress->value)
            ->orderByDesc('updated_at');

        $branchId = Auth::user()?->branch_id;
        if (filled($branchId)) {
            $bid = (int) $branchId;
            $query->where(function (Builder $q) use ($bid): void {
                $q->where('to_branch_id', $bid)
                    ->orWhere('from_branch_id', $bid);
            });
        }

        return $query;
    }

    private static function posSaleTransferLabel(ProductTransfer $transfer): string
    {
        $from = filled($transfer->fromBranch?->name) ? (string) $transfer->fromBranch?->name : 'Origen #'.$transfer->from_branch_id;
        $to = filled($transfer->toBranch?->name) ? (string) $transfer->toBranch?->name : 'Destino #'.$transfer->to_branch_id;
        $itemsCount = $transfer->items->count();

        return "{$transfer->code} · {$from} → {$to} · {$itemsCount} ítems";
    }

    private static function loadPosLineItemsFromSaleTransfer(int $transferId, Set $set): void
    {
        $transfer = self::posSaleTransferBaseQuery()
            ->whereKey($transferId)
            ->first();

        if (! $transfer instanceof ProductTransfer) {
            Notification::make()
                ->title('Traslado no disponible')
                ->body('El traslado no está «En proceso», no es de venta o su usuario no pertenece a la sucursal origen ni destino.')
                ->warning()
                ->send();

            $set('pos_sale_transfer_id', null);

            return;
        }

        $rows = $transfer->items
            ->sortBy('id')
            ->map(function ($item): array {
                $quantity = max(0.001, (float) ($item->quantity ?? 1));

                return [
                    'product_id' => filled($item->product_id) ? (int) $item->product_id : null,
                    'quantity' => round($quantity, 3),
                ];
            })
            ->filter(fn (array $row): bool => filled($row['product_id']))
            ->values()
            ->all();

        if ($rows === []) {
            Notification::make()
                ->title('Traslado sin productos')
                ->body('El traslado seleccionado no tiene ítems para cargar en caja.')
                ->warning()
                ->send();

            return;
        }

        $rows[] = [
            'product_id' => null,
            'quantity' => 1,
        ];

        $set('line_items', $rows);
        $set('pos_product_search', null);

        $branchId = Auth::user()?->branch_id;
        if (filled($branchId)) {
            $productIds = collect($rows)
                ->pluck('product_id')
                ->filter(fn (mixed $id): bool => filled($id))
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($productIds !== []) {
                self::warmPosDataForBranch((int) $branchId, $productIds);
            }
        }

        Notification::make()
            ->title('Ítems del traslado cargados')
            ->body('Se cargaron automáticamente los productos y cantidades del traslado seleccionado.')
            ->success()
            ->send();
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
            $rateLabel = '1 USD = Bs. '.number_format((float) $apiRate, 6, ',', '.').' (API oficial)';
        } elseif ($hasManual) {
            $rateLabel = '1 USD = Bs. '.number_format((float) $manual, 6, ',', '.').' (manual)';
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

    /**
     * Búsqueda de productos para traslados de venta: inventario en sucursal destino con existencia mayor que cero (misma presentación que la caja).
     *
     * @return array<int, string>
     */
    public static function saleTransferDestinationProductSearch(int $branchId, string $search, ?Get $get = null): array
    {
        if ($branchId <= 0) {
            return [];
        }

        return self::searchInventoryProductsForBranch($branchId, $search, $get, true);
    }

    public static function saleTransferDestinationProductOptionLabel(int $branchId, int $productId, ?Get $get = null): ?string
    {
        if ($branchId <= 0 || $productId <= 0) {
            return null;
        }

        return self::buildPosSearchOptionLabelFromCatalog($branchId, $productId, $get);
    }
}
