<?php

namespace App\Filament\Resources\Sales\Actions;

use App\Enums\ProductTransferStatus;
use App\Enums\SaleStatus;
use App\Enums\VenezuelanPagoMovilBank;
use App\Filament\Resources\Sales\SaleResource;
use App\Http\Requests\BdvConciliation\GetMovementRequest;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ConciliationBdv;
use App\Models\Inventory;
use App\Models\PhysicalCashBox;
use App\Models\PhysicalCashBoxMovement;
use App\Models\Product;
use App\Models\ProductTransfer;
use App\Models\Sale;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\BdvConciliation\BdvConciliationClient;
use App\Services\BdvConciliation\ManualBdvConciliationOtpService;
use App\Services\BdvConciliation\ManualBdvConciliationService;
use App\Services\Dolar\DolarApiDolaresService;
use App\Services\Dolar\DolarApiEstadoService;
use App\Services\Finance\AccountsReceivableFromSaleRegistrar;
use App\Services\Inventory\FefoLotBalanceQueryService;
use App\Services\Inventory\FefoLotSaleDispatchService;
use App\Services\Inventory\FefoPosAlertSaleLinker;
use App\Services\Inventory\PosFefoAlertLogRegistrar;
use App\Services\Inventory\PosInventoryStockFailureRegistrar;
use App\Services\Sales\CacheaConciliationRegistrar;
use App\Services\Sales\ClientCommercialDiscountResolver;
use App\Support\Cash\PhysicalCashBoxBillingGate;
use App\Support\Finance\DefaultIgtfRate;
use App\Support\Finance\DefaultVatRate;
use App\Support\Inventory\InventoryQuantityFormat;
use App\Support\Inventory\NearExpiryLotAlert;
use App\Support\Sales\CacheaPosPaymentSupport;
use App\Support\Sales\MixedPosPaymentSupport;
use App\Support\Sales\PosPaymentMethodOptions;
use App\Support\Sales\PosTerminalCheckout;
use App\Support\Sales\ProductUnitPricingForBranch;
use App\Support\Sales\SalesBillingAccess;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\BasePage;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Component;
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
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
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
    public const REGISTER_ACTION_NAME = 'posRegister';

    public const PAGO_MOVIL_CONCILIATION_ACTION_NAME = 'posPagoMovilConciliation';

    public const CREDIT_SALE_CONFIRMATION_ACTION_NAME = 'posCreditSaleConfirmation';

    /** Modal posterior a la venta: vueltos en efectivo USD y movimiento en caja física. */
    public const EFECTIVO_USD_POST_SALE_CHANGE_ACTION_NAME = 'posEfectivoUsdPostSaleChange';

    public const MIXED_EFECTIVO_VES_VUELTO_KIND = 'mixed_efectivo_ves_vuelto';

    /**
     * Abre la caja registradora (cliente, productos y cobro en un solo modal).
     */
    public static function makeRegister(): Action
    {
        return Action::make(self::REGISTER_ACTION_NAME)
            ->label('Caja')
            ->icon(Heroicon::Cube)
            ->color('primary')
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
            ])
            ->modalHeading('Caja registradora')
            ->modalDescription('Cargue productos, revise totales y confirme el cobro.')
            ->modalIcon(Heroicon::Banknotes)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Registrar venta')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCancelAction(fn (Action $action): Action => $action->color('danger'))
            ->visible(fn (): bool => PhysicalCashBoxBillingGate::userMayUseCashRegister(Auth::user()))
            ->registerModalActions([
                self::makeCreditSaleConfirmation(),
                self::makePagoMovilConciliation(),
                self::makeEfectivoUsdPostSaleChange(),
            ])
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $args = $action->getArguments();
                $clientId = $args['client_id'] ?? null;

                $state = self::defaultPosFormState();
                if (is_array($args['pos_data'] ?? null)) {
                    $state = array_merge($state, $args['pos_data']);
                }
                if (filled($args['pos_sale_transfer_id'] ?? null)) {
                    $prefillFromTransfer = self::prefillArgsFromSaleTransferId((int) $args['pos_sale_transfer_id']);
                    if (is_array($prefillFromTransfer['pos_data'] ?? null)) {
                        $state = array_merge($state, $prefillFromTransfer['pos_data']);
                    }
                    if (filled($prefillFromTransfer['client_id'] ?? null)) {
                        $clientId = (int) $prefillFromTransfer['client_id'];
                    }
                }
                if (filled($clientId)) {
                    $state['client_id'] = (int) $clientId;
                }

                $schema?->fill($state);

                $livewire = $action->getLivewire();
                if ($livewire instanceof LivewireComponent) {
                    if (filled($clientId)) {
                        $livewire->js(self::focusPosConsultButtonJs());
                    } else {
                        $livewire->js(self::mountPosRegisterFocusClientSelectJs());
                    }
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
                                ...self::posRegisterClientSchema(),
                                Hidden::make('bdv_pm_conciliated')
                                    ->default(false),
                                Hidden::make('credit_sale_confirmed')
                                    ->default(false),
                                ViewField::make('pos_product_consult')
                                    ->hiddenLabel()
                                    ->view('filament.sales.pos-product-consult')
                                    ->dehydrated(false)
                                    ->extraFieldWrapperAttributes([
                                        'class' => 'farmadoc-pos-consult-field',
                                    ])
                                    ->columnSpanFull(),
                                Placeholder::make('pos_cart_empty_hint')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->visible(fn (Get $get): bool => self::posLineItemsAreEmpty($get('line_items')))
                                    ->content(new HtmlString(
                                        '<p class="farmadoc-pos-cart-empty">No hay productos en la venta. Pulse «Consultar Productos» para buscar y añadir.</p>'
                                    ))
                                    ->columnSpanFull(),
                                Repeater::make('line_items')
                                    ->label('')
                                    ->reorderable(false)
                                    ->addable(false)
                                    ->defaultItems(0)
                                    ->minItems(0)
                                    ->live()
                                    ->partiallyRenderAfterActionsCalled(false)
                                    ->extraAttributes([
                                        'class' => 'farmadoc-pos-line-items-repeater fi-fixed-positioning-context',
                                    ])
                                    ->visible(fn (Get $get): bool => ! self::posLineItemsAreEmpty($get('line_items')))
                                    ->table([
                                        TableColumn::make('Producto')
                                            ->width('52%'),
                                        TableColumn::make('Cantidad')
                                            ->width('28%'),
                                        TableColumn::make('Total')
                                            ->alignment(Alignment::End)
                                            ->width('20%'),
                                    ])
                                    ->schema([
                                        Hidden::make('product_id')
                                            ->required(),
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => 'farmadoc-pos-cart-product-cell',
                                            ])
                                            ->schema([
                                                Placeholder::make('product_display')
                                                    ->hiddenLabel()
                                                    ->content(fn (Get $get): HtmlString => self::posCartProductCellHtml($get)),
                                                Placeholder::make('fefo_alert_banner')
                                                    ->hiddenLabel()
                                                    ->content(function (Get $get): HtmlString {
                                                        $branchId = Auth::user()?->branch_id;
                                                        $productId = $get('product_id');
                                                        if (blank($branchId) || blank($productId)) {
                                                            return new HtmlString('');
                                                        }

                                                        $alert = self::posNearExpiryLotAlert((int) $branchId, (int) $productId);
                                                        if (! $alert instanceof NearExpiryLotAlert) {
                                                            return new HtmlString('');
                                                        }

                                                        return new HtmlString($alert->bannerHtml());
                                                    })
                                                    ->visible(function (Get $get): bool {
                                                        $branchId = Auth::user()?->branch_id;
                                                        $productId = $get('product_id');
                                                        if (blank($branchId) || blank($productId)) {
                                                            return false;
                                                        }

                                                        return self::posNearExpiryLotAlert((int) $branchId, (int) $productId) instanceof NearExpiryLotAlert;
                                                    }),
                                            ]),
                                        TextInput::make('quantity')
                                            ->hiddenLabel()
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
                                                if ($available !== null && $requestedQuantity > ($available + 0.0001)) {
                                                    $product = self::posProduct($pid);
                                                    self::notifyPosQuantityExceedsStock(
                                                        $product instanceof Product ? $product->name : 'Producto #'.$pid,
                                                        $requestedQuantity,
                                                        $available,
                                                    );

                                                    return;
                                                }

                                                $product = self::posProduct($pid);
                                                if ($product instanceof Product) {
                                                    self::notifyPosNearExpiryLotIfNeeded(
                                                        (int) $branchId,
                                                        $pid,
                                                        $product->name,
                                                    );
                                                }
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
                                        Placeholder::make('line_total_display')
                                            ->hiddenLabel()
                                            ->dehydrated(false)
                                            ->content(fn (Get $get): HtmlString => self::posCartLineTotalHtml($get)),
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
                                        Toggle::make('pay_with_cachea')
                                            ->label(PosPaymentMethodOptions::cacheaToggleLabel())
                                            ->default(false)
                                            ->inline(true)
                                            ->live()
                                            ->dehydrated(true)
                                            ->disabled(fn (Get $get): bool => filter_var($get('generate_accounts_receivable') ?? false, FILTER_VALIDATE_BOOLEAN))
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                                if (filter_var($state, FILTER_VALIDATE_BOOLEAN)) {
                                                    $set('payment_method', PosPaymentMethodOptions::CACHEA);
                                                    $set('generate_accounts_receivable', false);
                                                    $set('bdv_pm_conciliated', false);
                                                    $set('cachea_complement_payment_method', 'efectivo_usd');

                                                    return;
                                                }

                                                $set('cachea_paid_amount', null);
                                                $set('cachea_complement_payment_method', 'efectivo_usd');

                                                if (($get('payment_method') ?? '') === PosPaymentMethodOptions::CACHEA) {
                                                    $set('payment_method', 'punto_venta_ves');
                                                }
                                            })
                                            ->columnSpanFull()
                                            ->extraFieldWrapperAttributes([
                                                'class' => 'farmadoc-pos-payment-toggle-row rounded-xl border border-zinc-300/60 bg-zinc-50/30 px-3 py-2.5 dark:border-white/20 dark:bg-white/5',
                                            ]),
                                        TextInput::make('cachea_paid_amount')
                                            ->label('Monto Inicial del Cliente')
                                            ->helperText('Indique el monto que el cliente pagó por Cashea (USD).')
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->prefix('$')
                                            ->live(debounce: 300)
                                            ->markAsRequired(fn (Get $get): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN))
                                            ->rules(fn (Get $get): array => [
                                                Rule::requiredIf(fn (): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN)),
                                                'numeric',
                                                'min:0.01',
                                            ])
                                            ->validationMessages([
                                                'required' => 'Indique el monto inicial del cliente.',
                                                'min' => 'El monto inicial del cliente debe ser mayor a cero.',
                                            ])
                                            ->dehydrated(fn (Get $get): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN))
                                            ->visible(fn (Get $get): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN)),
                                        Select::make('cachea_complement_payment_method')
                                            ->label('Metodo de Pago')
                                            ->helperText('Forma en que se cobra la inicial. Pago Multiple reparte ese monto entre varios métodos.')
                                            ->options(CacheaPosPaymentSupport::complementOptions())
                                            ->default('efectivo_usd')
                                            ->required()
                                            ->live()
                                            ->native(false)
                                            ->dehydrated(fn (Get $get): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN))
                                            ->visible(fn (Get $get): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN))
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                                if (! filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN)) {
                                                    return;
                                                }

                                                $set('bdv_pm_conciliated', false);

                                                if ((string) $state === 'mixed') {
                                                    $set('mixed_use_usd_portion', true);
                                                    $set('mixed_use_ves_portion', false);

                                                    return;
                                                }

                                                if ((string) $state !== 'pago_movil') {
                                                    return;
                                                }

                                                $total = self::computeSaleTotal($get);
                                                $paymentVes = CacheaPosPaymentSupport::breakdown(
                                                    $total,
                                                    [
                                                        'cachea_paid_amount' => $get('cachea_paid_amount'),
                                                        'cachea_complement_payment_method' => $state,
                                                    ],
                                                    self::effectiveVesUsdRate($get),
                                                )['payment_ves'];

                                                if ($paymentVes <= 0.00001) {
                                                    return;
                                                }

                                                $livewire = $component->getLivewire();
                                                if (! $livewire instanceof HasActions) {
                                                    return;
                                                }

                                                $reference = trim((string) ($get('reference') ?? ''));
                                                $livewire->mountAction(self::PAGO_MOVIL_CONCILIATION_ACTION_NAME, [
                                                    'pos_data' => self::posFormSnapshotFromGet($get),
                                                    'payment_ves' => $paymentVes,
                                                ]);
                                            }),
                                        TextEntry::make('cachea_remainder_preview')
                                            ->label('Financiamiento Cashea')
                                            ->state(function (Get $get): string {
                                                $total = self::computeSaleTotal($get);
                                                $remainder = CacheaPosPaymentSupport::remainder(
                                                    $total,
                                                    CacheaPosPaymentSupport::paidAmountFromGet($get),
                                                );

                                                return self::formatMoney($remainder);
                                            })
                                            ->helperText('Diferencia entre el total de la venta y el monto Cashea registrado.')
                                            ->dehydrated(false)
                                            ->visible(fn (Get $get): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN)),
                                        Toggle::make('generate_accounts_receivable')
                                            ->label('Vender a Credito')
                                            ->default(false)
                                            ->inline(true)
                                            ->live()
                                            ->disabled(fn (Get $get): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN))
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                                if (filter_var($state, FILTER_VALIDATE_BOOLEAN)) {
                                                    $set('payment_method', 'credito_cliente');
                                                    $set('pay_with_cachea', false);
                                                    $set('bdv_pm_conciliated', false);

                                                    return;
                                                }

                                                if (($get('payment_method') ?? '') === 'credito_cliente') {
                                                    $set('payment_method', 'punto_venta_ves');
                                                }
                                            })
                                            ->columnSpanFull()
                                            ->extraFieldWrapperAttributes([
                                                'class' => 'farmadoc-pos-payment-toggle-row rounded-xl border border-zinc-300/60 bg-zinc-50/30 px-3 py-2.5 dark:border-white/20 dark:bg-white/5',
                                            ]),
                                        Select::make('payment_method')
                                            ->label('Cobro')
                                            ->options(PosPaymentMethodOptions::posCobroOptions())
                                            ->getOptionLabelUsing(fn (?string $value): ?string => PosPaymentMethodOptions::posCobroOptionLabel($value))
                                            ->default('punto_venta_ves')
                                            ->required()
                                            ->dehydrated(true)
                                            ->live()
                                            ->disabled(fn (Get $get): bool => filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN)
                                                || filter_var($get('generate_accounts_receivable') ?? false, FILTER_VALIDATE_BOOLEAN))
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                                if ($state === 'credito_cliente') {
                                                    $set('generate_accounts_receivable', true);
                                                    $set('pay_with_cachea', false);
                                                    $set('bdv_pm_conciliated', false);

                                                    return;
                                                }

                                                $set('generate_accounts_receivable', false);

                                                if ($state === 'mixed') {
                                                    $set('mixed_use_usd_portion', true);
                                                    $set('mixed_use_ves_portion', false);
                                                }

                                                if ($state !== PosPaymentMethodOptions::CACHEA) {
                                                    $set('pay_with_cachea', false);
                                                }

                                                if (
                                                    $state === 'pago_movil'
                                                    || ($state === 'mixed' && self::mixedPaymentUsesPagoMovilFromGet($get))
                                                ) {
                                                    $set('bdv_pm_conciliated', false);
                                                    $livewire = $component->getLivewire();
                                                    if ($livewire instanceof HasActions) {
                                                        $paymentVes = $state === 'mixed'
                                                            ? MixedPosPaymentSupport::pagoMovilVesAmount(
                                                                self::mixedFormDataFromGet($get),
                                                                self::computeSaleTotal($get),
                                                                self::effectiveVesUsdRate($get),
                                                            )
                                                            : self::computePaymentBreakdownForForm($get)['payment_ves'];
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
                                        Select::make('pos_terminal_id')
                                            ->label('Punto de venta a utilizar')
                                            ->options(fn (): array => PosTerminalCheckout::optionsForAuthenticatedCashier())
                                            ->getOptionLabelUsing(function (?string $value): ?string {
                                                if (blank($value)) {
                                                    return null;
                                                }

                                                return PosTerminalCheckout::optionsForAuthenticatedCashier()[(int) $value] ?? null;
                                            })
                                            ->placeholder('Seleccione el punto de venta')
                                            ->helperText('Obligatorio. Solo se listan los puntos activos de su sucursal.')
                                            ->searchable()
                                            ->native(false)
                                            ->prefixIcon(Heroicon::BuildingLibrary)
                                            ->markAsRequired(fn (Get $get): bool => self::formUsesPointOfSale($get))
                                            ->rules(fn (Get $get): array => [
                                                Rule::requiredIf(fn (): bool => self::formUsesPointOfSale($get)),
                                            ])
                                            ->validationMessages([
                                                'required_if' => 'Debe seleccionar el punto de venta que va a utilizar.',
                                                'required' => 'Debe seleccionar el punto de venta que va a utilizar.',
                                            ])
                                            ->visible(fn (Get $get): bool => self::formUsesPointOfSale($get)),
                                        Placeholder::make('pos_terminal_missing_hint')
                                            ->hiddenLabel()
                                            ->content('No hay puntos de venta activos en su sucursal. Un administrador debe cargarlos en Configuración → Puntos de venta antes de cobrar por este medio.')
                                            ->visible(fn (Get $get): bool => self::formUsesPointOfSale($get)
                                                && PosTerminalCheckout::optionsForAuthenticatedCashier() === []),
                                        TextInput::make('card_last4')
                                            ->label('Últimos 4 dígitos de la tarjeta')
                                            ->helperText('recibe 4 digitos')
                                            ->inputMode('numeric')
                                            // Sin ->required(): evita el atributo HTML `required` y el tooltip nativo del navegador (poco contraste).
                                            ->markAsRequired(fn (Get $get): bool => self::formUsesPointOfSale($get))
                                            ->rules(fn (Get $get): array => [
                                                Rule::requiredIf(fn (): bool => self::formUsesPointOfSale($get)),
                                                'regex:/^\d{4}$/',
                                            ])
                                            ->regex('/^\d{4}$/')
                                            ->validationMessages([
                                                'required_if' => 'Debe indicar los últimos 4 dígitos de la tarjeta para Punto de Venta.',
                                                'required' => 'Debe indicar los últimos 4 dígitos de la tarjeta para Punto de Venta.',
                                                'regex' => 'Ingrese exactamente 4 dígitos numéricos.',
                                            ])
                                            ->visible(fn (Get $get): bool => self::formUsesPointOfSale($get)),
                                        Toggle::make('mixed_use_usd_portion')
                                            ->label('Parte del pago en US$')
                                            ->helperText('Combine dólares con el saldo en bolívares.')
                                            ->default(true)
                                            ->inline(true)
                                            ->live()
                                            ->dehydrated(true)
                                            ->visible(fn (Get $get): bool => self::showsMixedPaymentFields($get))
                                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                                if (filter_var($state, FILTER_VALIDATE_BOOLEAN)) {
                                                    $set('mixed_use_ves_portion', false);
                                                    $set('mixed_ves_split_amount_1', null);
                                                    $set('mixed_ves_split_cash_received_1', null);
                                                    $set('mixed_ves_split_cash_received_2', null);
                                                }
                                            })
                                            ->columnSpanFull()
                                            ->extraFieldWrapperAttributes([
                                                'class' => 'farmadoc-pos-payment-toggle-row rounded-xl border border-zinc-300/60 bg-zinc-50/30 px-3 py-2.5 dark:border-white/20 dark:bg-white/5',
                                            ]),
                                        Toggle::make('mixed_use_ves_portion')
                                            ->label('Parte del pago en VES (Bs.)')
                                            ->helperText('Divida el total entre dos métodos de pago en bolívares.')
                                            ->default(false)
                                            ->inline(true)
                                            ->live()
                                            ->dehydrated(true)
                                            ->visible(fn (Get $get): bool => self::showsMixedPaymentFields($get))
                                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                                if (filter_var($state, FILTER_VALIDATE_BOOLEAN)) {
                                                    $set('mixed_use_usd_portion', false);
                                                    $set('mixed_usd_paid', null);
                                                    $set('mixed_ves_cash_received', null);
                                                }
                                            })
                                            ->columnSpanFull()
                                            ->extraFieldWrapperAttributes([
                                                'class' => 'farmadoc-pos-payment-toggle-row rounded-xl border border-zinc-300/60 bg-zinc-50/30 px-3 py-2.5 dark:border-white/20 dark:bg-white/5',
                                            ]),
                                        TextInput::make('mixed_usd_paid')
                                            ->label('Pago en USD')
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->prefix('$')
                                            ->live(debounce: 300)
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, TextInput $component): void {
                                                if (! self::isMixedUsdModeFromGet($get)) {
                                                    return;
                                                }

                                                $set('bdv_pm_conciliated', false);
                                                self::mountMixedPagoMovilConciliationFromGet($get, $component);
                                            })
                                            ->visible(fn (Get $get): bool => self::isMixedUsdModeFromGet($get)),
                                        Select::make('mixed_ves_payment_method')
                                            ->label('Forma de pago del resto en VES')
                                            ->options(MixedPosPaymentSupport::vesMethodOptions())
                                            ->default('punto_venta_ves')
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                                if (! self::isMixedUsdModeFromGet($get)) {
                                                    return;
                                                }

                                                if ((string) $state !== 'efectivo_ves') {
                                                    $set('mixed_ves_cash_received', null);
                                                }

                                                $set('bdv_pm_conciliated', false);
                                                self::mountMixedPagoMovilConciliationFromGet($get, $component);
                                            })
                                            ->native(false)
                                            ->visible(fn (Get $get): bool => self::isMixedUsdModeFromGet($get)),
                                        TextInput::make('mixed_ves_cash_received')
                                            ->label('Bolívares recibidos en efectivo (cliente)')
                                            ->helperText(fn (Get $get): string => 'Mínimo a recibir: '
                                                .self::formatBolivaresReferenceFromVes(self::computePaymentBreakdownForForm($get)['payment_ves'])
                                                .'. El neto ingresa a la caja física en Bs.')
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->prefix('Bs')
                                            ->live(debounce: 300)
                                            ->markAsRequired(fn (Get $get): bool => self::isMixedUsdModeFromGet($get)
                                                && MixedPosPaymentSupport::selectedUsdModeVesMethod(self::mixedFormDataFromGet($get)) === 'efectivo_ves'
                                                && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001)
                                            ->rules(fn (Get $get): array => [
                                                Rule::requiredIf(fn (): bool => self::isMixedUsdModeFromGet($get)
                                                    && MixedPosPaymentSupport::selectedUsdModeVesMethod(self::mixedFormDataFromGet($get)) === 'efectivo_ves'
                                                    && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001),
                                            ])
                                            ->validationMessages([
                                                'required' => 'Indique los bolívares en efectivo que recibe del cliente.',
                                            ])
                                            ->visible(fn (Get $get): bool => self::isMixedUsdModeFromGet($get)
                                                && MixedPosPaymentSupport::selectedUsdModeVesMethod(self::mixedFormDataFromGet($get)) === 'efectivo_ves'
                                                && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001),
                                        TextEntry::make('mixed_ves_change_preview')
                                            ->label('Vuelto en bolívares (efectivo VES)')
                                            ->state(function (Get $get): string {
                                                $due = self::computePaymentBreakdownForForm($get)['payment_ves'];
                                                $received = round((float) ($get('mixed_ves_cash_received') ?? 0), 2);
                                                if ($received <= 0.00001) {
                                                    return '—';
                                                }

                                                return self::formatBolivaresReferenceFromVes(max(0.0, round($received - $due, 2)));
                                            })
                                            ->dehydrated(false)
                                            ->visible(fn (Get $get): bool => self::isMixedUsdModeFromGet($get)
                                                && MixedPosPaymentSupport::selectedUsdModeVesMethod(self::mixedFormDataFromGet($get)) === 'efectivo_ves'
                                                && self::computePaymentBreakdownForForm($get)['payment_ves'] > 0.00001),
                                        Select::make('mixed_ves_split_method_1')
                                            ->label('Primer pago en VES')
                                            ->options(MixedPosPaymentSupport::vesMethodOptions())
                                            ->default('punto_venta_ves')
                                            ->required()
                                            ->live()
                                            ->native(false)
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                                if (! self::isMixedVesModeFromGet($get)) {
                                                    return;
                                                }

                                                if ((string) $state !== 'efectivo_ves') {
                                                    $set('mixed_ves_split_cash_received_1', null);
                                                }

                                                $set('bdv_pm_conciliated', false);
                                                self::mountMixedPagoMovilConciliationFromGet($get, $component);
                                            })
                                            ->visible(fn (Get $get): bool => self::isMixedVesModeFromGet($get)),
                                        TextInput::make('mixed_ves_split_amount_1')
                                            ->label('Monto del primer pago (Bs.)')
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->prefix('Bs')
                                            ->live(debounce: 300)
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, TextInput $component): void {
                                                if (! self::isMixedVesModeFromGet($get)) {
                                                    return;
                                                }

                                                $set('bdv_pm_conciliated', false);
                                                self::mountMixedPagoMovilConciliationFromGet($get, $component);
                                            })
                                            ->visible(fn (Get $get): bool => self::isMixedVesModeFromGet($get)),
                                        TextInput::make('mixed_ves_split_cash_received_1')
                                            ->label('Efectivo recibido — primer pago (Bs.)')
                                            ->helperText(fn (Get $get): string => 'Mínimo: '
                                                .self::formatBolivaresReferenceFromVes((float) ($get('mixed_ves_split_amount_1') ?? 0))
                                                .'. Aumenta la caja física en Bs.')
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->prefix('Bs')
                                            ->live(debounce: 300)
                                            ->visible(fn (Get $get): bool => self::isMixedVesModeFromGet($get)
                                                && ($get('mixed_ves_split_method_1') ?? '') === 'efectivo_ves'
                                                && (float) ($get('mixed_ves_split_amount_1') ?? 0) > 0.00001),
                                        Select::make('mixed_ves_split_method_2')
                                            ->label('Segundo pago en VES')
                                            ->options(MixedPosPaymentSupport::vesMethodOptions())
                                            ->default('transfer_ves')
                                            ->required()
                                            ->live()
                                            ->native(false)
                                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                                if (! self::isMixedVesModeFromGet($get)) {
                                                    return;
                                                }

                                                if ((string) $state !== 'efectivo_ves') {
                                                    $set('mixed_ves_split_cash_received_2', null);
                                                }

                                                $set('bdv_pm_conciliated', false);
                                                self::mountMixedPagoMovilConciliationFromGet($get, $component);
                                            })
                                            ->visible(fn (Get $get): bool => self::isMixedVesModeFromGet($get)),
                                        TextEntry::make('mixed_ves_split_amount_2_preview')
                                            ->label('Monto del segundo pago (Bs.)')
                                            ->state(function (Get $get): string {
                                                $totalVes = self::computePaymentBreakdownForForm($get)['payment_ves'];
                                                $first = round((float) ($get('mixed_ves_split_amount_1') ?? 0), 2);

                                                return self::formatBolivaresReferenceFromVes(max(0.0, round($totalVes - $first, 2)));
                                            })
                                            ->dehydrated(false)
                                            ->visible(fn (Get $get): bool => self::isMixedVesModeFromGet($get)),
                                        TextInput::make('mixed_ves_split_cash_received_2')
                                            ->label('Efectivo recibido — segundo pago (Bs.)')
                                            ->helperText(function (Get $get): string {
                                                $totalVes = self::computePaymentBreakdownForForm($get)['payment_ves'];
                                                $due = max(0.0, round($totalVes - (float) ($get('mixed_ves_split_amount_1') ?? 0), 2));

                                                return 'Mínimo: '.self::formatBolivaresReferenceFromVes($due).'. Aumenta la caja física en Bs.';
                                            })
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->prefix('Bs')
                                            ->live(debounce: 300)
                                            ->visible(fn (Get $get): bool => self::isMixedVesModeFromGet($get)
                                                && ($get('mixed_ves_split_method_2') ?? '') === 'efectivo_ves'
                                                && self::mixedVesSplitSecondAmountFromGet($get) > 0.00001),
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
                                            ->helperText('Obligatoria para transferencia VES, Zelle, complemento Cashea o pagos mixtos con parte en bolívares. En Pago Móvil la referencia se indica en la ventana de conciliación BDV.')
                                            ->maxLength(255)
                                            ->dehydratedWhenHidden()
                                            ->markAsRequired(fn (Get $get): bool => self::posRequiresPaymentReference($get))
                                            ->rules(fn (Get $get): array => [
                                                Rule::requiredIf(fn (): bool => self::posRequiresPaymentReference($get)),
                                            ])
                                            ->validationMessages([
                                                'required' => 'Debe indicar una referencia de pago para transferencia VES, Zelle, complemento Cashea o la parte en bolívares del pago mixto.',
                                            ])
                                            ->visible(fn (Get $get): bool => self::posRequiresPaymentReference($get)),
                                    ]),
                            ])
                            ->columnSpan(['lg' => 4]),
                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Action $action) {
                if (! PhysicalCashBoxBillingGate::userMayUseCashRegister(Auth::user())) {
                    Notification::make()
                        ->title('Caja no disponible')
                        ->body(PhysicalCashBoxBillingGate::cashRegisterUnavailableMessage(Auth::user()))
                        ->warning()
                        ->send();

                    return;
                }

                if (! SalesBillingAccess::userCanBill(Auth::user())) {
                    Notification::make()
                        ->title('Caja no disponible')
                        ->body('Su rol no puede registrar ventas en caja.')
                        ->warning()
                        ->send();

                    return;
                }

                $data['client_id'] = self::resolvePosClientIdFromRegisterData($data, $action);
                $data = self::enrichPosRegisterCacheaData($data, $action);
                $data = self::enrichPosRegisterPagoMovilData($data, $action);

                self::posSaleRegisterTrace('register_action_entered', [
                    'payment_method' => PosPaymentMethodOptions::resolveFromPosRegisterData($data),
                    'pay_with_cachea' => $data['pay_with_cachea'] ?? null,
                    'cachea_paid_amount' => $data['cachea_paid_amount'] ?? null,
                    'bdv_pm_conciliated' => $data['bdv_pm_conciliated'] ?? null,
                    'reference_len' => strlen(trim((string) ($data['reference'] ?? ''))),
                    'line_items_count' => count($data['line_items'] ?? []),
                    'client_id' => $data['client_id'] ?? null,
                ]);

                $branchId = Auth::user()?->branch_id;

                if (blank($branchId)) {
                    self::logPosSaleBlocked('usuario_sin_sucursal');
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

                $paymentMethod = PosPaymentMethodOptions::resolveFromPosRegisterData($data);
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
                    self::logPosSaleBlocked('sin_productos');
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
                        'direct_price',
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

                $clientIdForDiscount = filled($data['client_id'] ?? null) ? (int) $data['client_id'] : null;
                $discountPercent = app(ClientCommercialDiscountResolver::class)->percentForClientId($clientIdForDiscount);

                $pricing = self::finalizePosPricingFromValidLines(
                    $validLines,
                    $paymentMethod,
                    discountPercent: $discountPercent,
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
                    $originalLineGross = round($qty * $unit, 2);
                    $lineDiscountAmount = round(max(0.0, $originalLineGross - $lineSubtotal), 2);

                    $payloadItems[] = [
                        'product_id' => $productId,
                        'inventory_id' => (int) $inventory->id,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'unit_cost' => $unitCost,
                        'discount_amount' => $lineDiscountAmount,
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
                $cacheaBreakdown = null;

                if ($paymentMethod === PosPaymentMethodOptions::CACHEA
                    || CacheaPosPaymentSupport::isCacheaPayment($paymentMethod, $data['pay_with_cachea'] ?? false)) {
                    $paymentMethod = PosPaymentMethodOptions::CACHEA;

                    $cacheaPaid = CacheaPosPaymentSupport::paidAmountFromData($data);
                    if ($cacheaPaid <= 0.00001) {
                        Notification::make()
                            ->title('Indique el monto Cashea')
                            ->body('Debe registrar el monto pagado por el cliente con Cashea.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($cacheaPaid > $documentTotal + 0.02) {
                        Notification::make()
                            ->title('Monto Cashea inválido')
                            ->body('El monto pagado con Cashea no puede superar el total de la venta ('.self::formatMoney($documentTotal).').')
                            ->danger()
                            ->send();

                        return;
                    }

                    $cacheaBreakdown = CacheaPosPaymentSupport::breakdown($documentTotal, $data, $vesUsdRate);
                }

                if ($paymentMethod === 'mixed'
                    || ($cacheaBreakdown !== null && ($cacheaBreakdown['complement_payment_method'] ?? '') === 'mixed')) {
                    $mixedTarget = $cacheaBreakdown !== null && ($cacheaBreakdown['complement_payment_method'] ?? '') === 'mixed'
                        ? (float) $cacheaBreakdown['cachea_paid_amount']
                        : $documentTotal;
                    $mixedValidation = MixedPosPaymentSupport::validateBeforeRegister(
                        $data,
                        $mixedTarget,
                        $vesUsdRate,
                    );
                    if (! $mixedValidation['valid']) {
                        Notification::make()
                            ->title('Pago múltiple incompleto')
                            ->body($mixedValidation['message'] ?? 'Revise los montos y métodos del pago múltiple.')
                            ->danger()
                            ->send();

                        return;
                    }
                }

                $requiresVesUsdRate = ($paymentMethod === 'mixed'
                    ? MixedPosPaymentSupport::requiresVesConversion($documentTotal, $data)
                    : self::requiresVesConversion($paymentMethod, $documentTotal, (float) ($data['mixed_usd_paid'] ?? 0)))
                    || ($paymentMethod === 'efectivo_usd' && $documentTotal > 0.00001)
                    || ($cacheaBreakdown !== null && $cacheaBreakdown['payment_usd'] > 0.00001);

                if ($cacheaBreakdown !== null && $cacheaBreakdown['remainder'] > 0.00001) {
                    $requiresVesUsdRate = $requiresVesUsdRate || in_array(
                        $cacheaBreakdown['complement_payment_method'],
                        ['transfer_ves', 'pago_movil', 'efectivo_ves', 'punto_venta_ves'],
                        true,
                    );
                }

                if ($documentTotal > 0 && $requiresVesUsdRate && $vesUsdRate <= 0) {
                    Notification::make()
                        ->title('Indique la tasa Bs. por USD')
                        ->body('La API no devolvió una tasa válida. Ingrese manualmente el valor del bolívar para continuar.')
                        ->danger()
                        ->send();

                    return;
                }

                if ($cacheaBreakdown !== null) {
                    $paymentUsd = $cacheaBreakdown['payment_usd'];
                    $paymentVes = ($cacheaBreakdown['complement_payment_method'] ?? '') === 'mixed'
                        ? $cacheaBreakdown['payment_ves']
                        : $cacheaBreakdown['payment_ves_equivalent'];
                } else {
                    if ($paymentMethod === 'mixed') {
                        $mixedBreakdown = MixedPosPaymentSupport::breakdown($documentTotal, $vesUsdRate, $data);
                        $paymentUsd = (float) ($mixedBreakdown['payment_usd'] ?? 0.0);
                        $paymentVes = (float) ($mixedBreakdown['payment_ves'] ?? 0.0);
                    } else {
                        [$paymentUsd, $paymentVes] = self::resolvePaymentAmounts(
                            $documentTotal,
                            $paymentMethod,
                            mixedUsdPaid: (float) ($data['mixed_usd_paid'] ?? 0),
                            vesUsdRate: $vesUsdRate
                        );
                    }
                }

                $pagoMovilVesAmount = $paymentMethod === 'mixed'
                    ? MixedPosPaymentSupport::pagoMovilVesAmount($data, $documentTotal, $vesUsdRate)
                    : ($cacheaBreakdown !== null && ($cacheaBreakdown['complement_payment_method'] ?? '') === 'mixed'
                        ? MixedPosPaymentSupport::pagoMovilVesAmount($data, (float) $cacheaBreakdown['cachea_paid_amount'], $vesUsdRate)
                        : ($cacheaBreakdown !== null
                            ? $cacheaBreakdown['payment_ves']
                            : $paymentVes));

                $recentConciliation = null;

                if (
                    (
                        self::usesPagoMovilForVesPortion($paymentMethod, $mixedVesPaymentMethod, $data, $documentTotal, $vesUsdRate)
                        || CacheaPosPaymentSupport::usesPagoMovilComplement($paymentMethod, $data, $documentTotal, $vesUsdRate)
                    )
                    && $pagoMovilVesAmount > 0.00001
                ) {
                    if (app()->isLocal()) {
                        if ($paymentReference === '') {
                            $paymentReference = 'DEV-PM-'.now()->format('YmdHis');
                        }

                        Log::info('bdv.pos_conciliation.local_bypass', [
                            'module' => 'pos_caja',
                            'message' => 'Bypass local activo: venta permitida sin bloqueo por bandera bdv_pm_conciliated.',
                            'branch_id' => $branchId,
                            'payment_ves' => $pagoMovilVesAmount,
                            'reference' => $paymentReference,
                        ]);
                    }

                    $alreadyConciliated = filter_var($data['bdv_pm_conciliated'] ?? false, FILTER_VALIDATE_BOOL);
                    if (app()->isLocal()) {
                        $alreadyConciliated = true;
                    }
                    $recentConciliation = null;
                    if (! $alreadyConciliated) {
                        $recentConciliation = self::findRecentSuccessfulBdvConciliation(
                            $branchId,
                            $paymentReference,
                            $pagoMovilVesAmount,
                        );

                        $alreadyConciliated = $recentConciliation instanceof ConciliationBdv;
                    } else {
                        $recentConciliation = self::findRecentSuccessfulBdvConciliation(
                            $branchId,
                            $paymentReference,
                            $pagoMovilVesAmount,
                        );
                    }

                    if ($paymentReference === '') {
                        if (! $recentConciliation instanceof ConciliationBdv) {
                            $recentConciliation = self::findLatestSuccessfulBdvConciliationForCashier($branchId);
                        }

                        $resolvedReference = self::resolvePagoMovilPaymentReference(
                            $branchId,
                            $pagoMovilVesAmount,
                            $paymentReference,
                            $recentConciliation,
                        );
                        if ($resolvedReference !== '') {
                            $paymentReference = $resolvedReference;
                            self::posSaleRegisterTrace('pm_reference_resolved', [
                                'reference_suffix' => strlen($resolvedReference) >= 4
                                    ? substr($resolvedReference, -4)
                                    : $resolvedReference,
                            ]);
                        }
                    }

                    if (! $alreadyConciliated) {
                        self::logPosSaleBlocked('pago_movil_sin_conciliar', [
                            'payment_ves' => $pagoMovilVesAmount,
                            'reference_len' => strlen($paymentReference),
                            'bdv_pm_conciliated' => $data['bdv_pm_conciliated'] ?? null,
                        ]);
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

                    if ($recentConciliation instanceof ConciliationBdv) {
                        AuditLogger::record(
                            'pos_caja_bdv_conciliation_reused',
                            'Caja · Se reutilizó conciliación BDV reciente para continuar la venta',
                            properties: [
                                'module' => 'pos_caja',
                                'conciliation_id' => (int) $recentConciliation->id,
                                'reference' => (string) $recentConciliation->reference,
                                'amount' => (float) $recentConciliation->amount,
                            ],
                        );
                    }
                }

                $requiresPosTerminal = (
                    self::usesPointOfSaleForVesPortion($paymentMethod, $mixedVesPaymentMethod, $data, $documentTotal, $vesUsdRate)
                    || CacheaPosPaymentSupport::usesPointOfSaleComplement($paymentMethod, $data, $documentTotal, $vesUsdRate)
                ) && $paymentVes > 0.00001;

                $resolvedPosTerminal = $requiresPosTerminal
                    ? PosTerminalCheckout::findActiveForBranch($branchId, $data['pos_terminal_id'] ?? null)
                    : null;

                if ($requiresPosTerminal && $resolvedPosTerminal === null) {
                    $hasBranchTerminals = PosTerminalCheckout::optionsForBranch($branchId) !== [];
                    AuditLogger::record(
                        'pos_caja_sale_blocked',
                        'Caja · No se registró la venta: punto de venta no seleccionado',
                        properties: [
                            'module' => 'pos_caja',
                            'reason' => $hasBranchTerminals ? 'punto_venta_sin_terminal' : 'punto_venta_sin_terminales_sucursal',
                        ],
                    );
                    Notification::make()
                        ->title($hasBranchTerminals ? 'Seleccione el punto de venta' : 'No hay puntos de venta')
                        ->body($hasBranchTerminals
                            ? 'Para cobrar por Punto de Venta debe indicar qué punto de su sucursal va a utilizar.'
                            : 'No hay puntos de venta activos en su sucursal. Un administrador debe cargarlos en Configuración → Puntos de venta.')
                        ->danger()
                        ->send();
                    $action->halt();

                    return;
                }

                if ($requiresPosTerminal && ! preg_match('/^\d{4}$/', $cardLast4)) {
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

                $posTerminalCode = filled($resolvedPosTerminal?->code) ? (string) $resolvedPosTerminal->code : null;

                if ($paymentMethod === 'punto_venta_ves') {
                    $paymentReference = ($posTerminalCode !== null ? 'POS '.$posTerminalCode : 'POS').' ****'.$cardLast4;
                } elseif (
                    $paymentMethod === PosPaymentMethodOptions::CACHEA
                    && CacheaPosPaymentSupport::usesPointOfSaleComplement($paymentMethod, $data, $documentTotal, $vesUsdRate)
                ) {
                    $paymentReference = ($posTerminalCode !== null ? 'CASHEA POS '.$posTerminalCode : 'CASHEA POS').' ****'.$cardLast4;
                } elseif (
                    $paymentMethod === 'mixed'
                    && MixedPosPaymentSupport::vesPortionUsesPointOfSale($data, $documentTotal, $vesUsdRate)
                ) {
                    $paymentReference = ($posTerminalCode !== null ? 'MIXTO POS '.$posTerminalCode : 'MIXTO POS').' ****'.$cardLast4;
                }

                $shouldRequireReference = PosPaymentMethodOptions::requiresPaymentReference($paymentMethod)
                    || CacheaPosPaymentSupport::complementRequiresReference($paymentMethod, $data, $documentTotal, $vesUsdRate)
                    || (
                        $paymentMethod === 'mixed'
                        && MixedPosPaymentSupport::requiresPaymentReference($data, $documentTotal, $vesUsdRate)
                    );

                if ($shouldRequireReference && $paymentReference === '') {
                    Notification::make()
                        ->title('Indique la referencia de pago')
                        ->body('La referencia es obligatoria para transferencia VES, Zelle, complemento Cashea o la parte en bolívares del pago mixto.')
                        ->danger()
                        ->send();

                    $action->halt();

                    return;
                }

                if (
                    self::usesPagoMovilForVesPortion($paymentMethod, $mixedVesPaymentMethod, $data, $documentTotal, $vesUsdRate)
                    && $pagoMovilVesAmount > 0.00001
                    && $paymentReference === ''
                ) {
                    $paymentReference = self::resolvePagoMovilPaymentReference(
                        $branchId,
                        $pagoMovilVesAmount,
                        '',
                        $recentConciliation instanceof ConciliationBdv
                            ? $recentConciliation
                            : self::findLatestSuccessfulBdvConciliationForCashier($branchId),
                    );
                }

                if (
                    self::usesPagoMovilForVesPortion($paymentMethod, $mixedVesPaymentMethod, $data, $documentTotal, $vesUsdRate)
                    && $pagoMovilVesAmount > 0.00001
                    && $paymentReference === ''
                ) {
                    self::logPosSaleBlocked('pago_movil_sin_referencia', [
                        'payment_ves' => $pagoMovilVesAmount,
                    ]);
                    Notification::make()
                        ->title('Falta la referencia del Pago Móvil')
                        ->body('Valide el pago en la ventana de conciliación BDV para registrar la referencia antes de cerrar la venta.')
                        ->danger()
                        ->send();

                    $action->halt();

                    return;
                }

                $bcvVesPerUsd = ($vesUsdRate > 0.0 && ($paymentVes > 0.00001 || $paymentMethod === 'efectivo_usd'))
                    ? $vesUsdRate
                    : null;

                $actor = Auth::user()?->email
                    ?? Auth::user()?->name
                    ?? 'sistema';

                self::posSaleRegisterTrace('register_transaction_start', [
                    'payment_method' => $paymentMethod,
                    'document_total' => $documentTotal,
                    'payment_ves' => $paymentVes,
                    'reference_suffix' => strlen($paymentReference) >= 2
                        ? substr($paymentReference, -2)
                        : null,
                    'items_count' => count($payloadItems),
                ]);

                try {
                    $sale = DB::transaction(function () use ($branchId, $data, $payloadItems, $lines, $products, $subtotal, $taxTotal, $igtfTotal, $discountTotal, $documentTotal, $actor, $paymentMethod, $paymentUsd, $paymentVes, $paymentReference, $bcvVesPerUsd, $generateAccountsReceivable, $vesUsdRate, $resolvedPosTerminal): Sale {
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
                                    'Stock insuficiente para '.$productName.'. Disponible: '.InventoryQuantityFormat::displayDot($available).'.'
                                );
                            }
                        }

                        $saleAttributes = [
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
                            'notes' => self::resolvePosSaleNotes($paymentMethod, $generateAccountsReceivable, $data, $documentTotal, $vesUsdRate),
                            'sold_at' => now(),
                            'created_by' => $actor,
                            'updated_by' => $actor,
                        ];

                        if (SchemaFacade::hasColumn('sales', 'efectivo_usd_caja_meta')) {
                            $saleAttributes['efectivo_usd_caja_meta'] = null;
                        }

                        if (SchemaFacade::hasColumn('sales', 'pos_terminal_id')) {
                            $saleAttributes['pos_terminal_id'] = $resolvedPosTerminal?->id;
                        }

                        $sale = Sale::query()->create($saleAttributes);

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

                            app(FefoLotSaleDispatchService::class)->dispatchForSaleLine(
                                (int) $branchId,
                                $product,
                                $qty,
                                $inventory,
                                $sale,
                                $actor,
                            );
                        }

                        if ($paymentMethod === 'credito_cliente' && $generateAccountsReceivable) {
                            AccountsReceivableFromSaleRegistrar::register($sale, $actor);
                        }

                        if ($paymentMethod === PosPaymentMethodOptions::CACHEA) {
                            CacheaConciliationRegistrar::registerFromPosSale(
                                $sale,
                                $data,
                                (float) ($bcvVesPerUsd ?? 0.0),
                                $paymentReference !== '' ? $paymentReference : null,
                            );
                        }

                        $user = Auth::user();
                        if ($user instanceof User) {
                            self::recordMixedEfectivoVesPhysicalCashBoxMovementIfNeeded(
                                sale: $sale,
                                user: $user,
                                data: $data,
                                paymentMethod: $paymentMethod,
                                actor: $actor,
                            );

                            app(FefoPosAlertSaleLinker::class)->linkSale(
                                $sale,
                                (int) $branchId,
                                (int) $user->id,
                                $qtyByProduct,
                            );
                        }

                        return $sale;
                    });
                } catch (RuntimeException $e) {
                    self::logPosSaleBlocked('error_transaccion', [
                        'message' => Str::limit($e->getMessage(), 500),
                    ]);
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

                self::posSaleRegisterTrace('register_completed', [
                    'sale_id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'total' => (float) $sale->total,
                ]);

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
                        'cachea_resto' => $sale->payment_method === PosPaymentMethodOptions::CACHEA
                            && is_array($cacheaBreakdown)
                            ? $cacheaBreakdown['remainder']
                            : null,
                    ],
                );

                $saleSuccessBody = 'Total '.self::formatMoney($documentTotal).' · '.$sale->sale_number;
                if ($sale->payment_method === 'credito_cliente') {
                    $saleSuccessBody .= ' · Cuenta por cobrar creada.';
                }
                if ($sale->payment_method === PosPaymentMethodOptions::CACHEA) {
                    $conciliation = $sale->conciliationCachea;
                    if ($conciliation !== null) {
                        $saleSuccessBody .= ' · Resto Cashea '.self::formatMoney((float) $conciliation->remainder).'.';
                    }
                }
                if (PhysicalCashBoxMovement::query()
                    ->where('sale_id', $sale->id)
                    ->where('kind', self::MIXED_EFECTIVO_VES_VUELTO_KIND)
                    ->exists()) {
                    $saleSuccessBody .= ' · Efectivo VES registrado en caja física.';
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

                $livewire = $action->getLivewire();
                if ($sale->payment_method === 'efectivo_usd' && $livewire instanceof HasActions) {
                    $livewire->mountAction(self::EFECTIVO_USD_POST_SALE_CHANGE_ACTION_NAME, [
                        'sale_id' => $sale->id,
                        'redirect_url' => $nextUrl,
                    ]);

                    return;
                }

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
     * Tras una venta en efectivo USD: asistente de vueltos y registro en {@see PhysicalCashBoxMovement}.
     */
    public static function makeEfectivoUsdPostSaleChange(): Action
    {
        return Action::make(self::EFECTIVO_USD_POST_SALE_CHANGE_ACTION_NAME)
            ->label('Vueltos efectivo USD')
            ->modalHeading('Vueltos en efectivo USD')
            ->modalDescription('Los importes en bolívares usan la tasa BCV guardada en la venta. Si registra el vuelto, quedará un movimiento en la tabla de caja física para conciliación.')
            ->modalIcon(Heroicon::Banknotes)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Continuar')
            ->closeModalByClickingAway(false)
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $args = $action->getArguments();
                $saleId = (int) ($args['sale_id'] ?? 0);
                $redirectUrl = (string) ($args['redirect_url'] ?? '');
                $sale = Sale::query()->find($saleId);
                $invalid = ! $sale instanceof Sale || $sale->payment_method !== 'efectivo_usd';
                $alreadyRecorded = false;
                if (! $invalid) {
                    $alreadyRecorded = PhysicalCashBoxMovement::query()->where('sale_id', $sale->id)->exists();
                }

                $snapshotTotal = $sale instanceof Sale ? (float) $sale->total : 0.0;
                $snapshotBcv = $sale instanceof Sale && $sale->bcv_ves_per_usd !== null
                    ? (float) $sale->bcv_ves_per_usd
                    : 0.0;

                $schema?->fill([
                    'efd_sale_id' => $saleId,
                    'efd_redirect_url' => $redirectUrl,
                    'efd_snapshot_total' => $snapshotTotal,
                    'efd_snapshot_bcv' => $snapshotBcv,
                    'efd_invalid' => $invalid,
                    'efd_already_recorded' => $alreadyRecorded,
                    'efd_record_change' => ! $alreadyRecorded && ! $invalid,
                    'efd_sale_number' => $sale instanceof Sale ? (string) $sale->sale_number : '',
                    'cash_usd_client_bill' => null,
                    'cash_usd_drawer_out' => 0,
                ]);
            })
            ->schema([
                Hidden::make('efd_sale_id')->dehydrated(),
                Hidden::make('efd_redirect_url')->dehydrated(),
                Hidden::make('efd_snapshot_total')->dehydrated(),
                Hidden::make('efd_snapshot_bcv')->dehydrated(),
                Hidden::make('efd_invalid')->default(false)->dehydrated(),
                Hidden::make('efd_already_recorded')->default(false)->dehydrated(),
                Hidden::make('efd_sale_number')->dehydrated(),
                TextEntry::make('efd_error_banner')
                    ->hiddenLabel()
                    ->state('No se pudo cargar la venta en efectivo USD. Pulse Continuar para cerrar.')
                    ->color('danger')
                    ->visible(fn (Get $get): bool => self::truthyFormBool($get('efd_invalid'))),
                TextEntry::make('efd_already_banner')
                    ->hiddenLabel()
                    ->state('Los vueltos de esta venta ya fueron registrados en caja física.')
                    ->visible(fn (Get $get): bool => self::truthyFormBool($get('efd_already_recorded'))),
                TextEntry::make('efd_sale_context')
                    ->hiddenLabel()
                    ->weight(FontWeight::SemiBold)
                    ->state(fn (Get $get): string => 'Venta '.($get('efd_sale_number') ?? '').' · Total '.self::formatMoney((float) ($get('efd_snapshot_total') ?? 0)))
                    ->visible(fn (Get $get): bool => ! self::truthyFormBool($get('efd_invalid'))),
                Toggle::make('efd_record_change')
                    ->label('¿Registrar vuelto y salida de USD desde la caja?')
                    ->helperText('Desactive solo si no dará vuelto desde caja o lo registrará después; no se creará movimiento de conciliación.')
                    ->default(true)
                    ->inline(true)
                    ->live()
                    ->visible(fn (Get $get): bool => ! self::truthyFormBool($get('efd_invalid'))
                        && ! self::truthyFormBool($get('efd_already_recorded'))),
                TextInput::make('cash_usd_client_bill')
                    ->label('Billete del cliente (USD)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->prefix('$')
                    ->live(debounce: 200)
                    ->helperText('Denominación del billete o monto en efectivo USD que entregó el cliente.')
                    ->visible(fn (Get $get): bool => ! self::truthyFormBool($get('efd_invalid'))
                        && ! self::truthyFormBool($get('efd_already_recorded'))
                        && self::truthyFormBool($get('efd_record_change'))),
                Fieldset::make('Vuelto sobre el billete')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('efd_change_on_bill_usd')
                                    ->label('Vuelto en USD')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold)
                                    ->state(fn (Get $get): string => self::formatPostSaleEfectivoUsdChangeOnBillUsdLabel($get))
                                    ->dehydrated(false),
                                TextEntry::make('efd_change_on_bill_ves')
                                    ->label('Equivalente en VES')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold)
                                    ->state(fn (Get $get): string => self::formatPostSaleEfectivoUsdChangeOnBillVesLabel($get))
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => ! self::truthyFormBool($get('efd_invalid'))
                        && ! self::truthyFormBool($get('efd_already_recorded'))
                        && self::truthyFormBool($get('efd_record_change'))),
                Fieldset::make('Vuelto según USD entregado desde caja')
                    ->schema([
                        TextInput::make('cash_usd_drawer_out')
                            ->label('USD retirados de la caja para vueltos')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('$')
                            ->default(0)
                            ->live(debounce: 200)
                            ->helperText(fn (Get $get): string => self::postSaleEfectivoUsdDrawerOutHelperText($get)),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('efd_final_change_usd')
                                    ->label('Vuelto en USD (restante)')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold)
                                    ->state(fn (Get $get): string => self::formatPostSaleEfectivoUsdFinalChangeUsdLabel($get))
                                    ->dehydrated(false),
                                TextEntry::make('efd_final_change_ves')
                                    ->label('Vuelto en VES (restante)')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold)
                                    ->state(fn (Get $get): string => self::formatPostSaleEfectivoUsdFinalChangeVesLabel($get))
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => ! self::truthyFormBool($get('efd_invalid'))
                        && ! self::truthyFormBool($get('efd_already_recorded'))
                        && self::truthyFormBool($get('efd_record_change'))),
            ])
            ->action(function (array $data) {
                $redirectUrl = trim((string) ($data['efd_redirect_url'] ?? ''));
                $fallbackUrl = SaleResource::getUrl('index');
                $redirectTarget = $redirectUrl !== '' ? $redirectUrl : $fallbackUrl;
                $redirect = static fn () => redirect()->to($redirectTarget);

                if (self::truthyFormBool($data['efd_invalid'] ?? false)) {
                    return $redirect();
                }

                if (self::truthyFormBool($data['efd_already_recorded'] ?? false)) {
                    return $redirect();
                }

                if (! self::truthyFormBool($data['efd_record_change'] ?? true)) {
                    AuditLogger::record(
                        'pos_efectivo_usd_caja_change_skipped',
                        'Caja · Vueltos USD: usuario omitió registro en caja física',
                        Sale::class,
                        isset($data['efd_sale_id']) ? (int) $data['efd_sale_id'] : null,
                        isset($data['efd_sale_number']) ? (string) $data['efd_sale_number'] : null,
                        ['module' => 'pos_caja'],
                    );

                    return $redirect();
                }

                $saleId = (int) ($data['efd_sale_id'] ?? 0);
                $sale = Sale::query()->find($saleId);
                if (! $sale instanceof Sale || $sale->payment_method !== 'efectivo_usd') {
                    return $redirect();
                }

                $user = Auth::user();
                if (! $user instanceof User) {
                    return $redirect();
                }

                $billRaw = $data['cash_usd_client_bill'] ?? null;
                if (! is_numeric($billRaw) || (float) $billRaw <= 0) {
                    Notification::make()
                        ->title('Indique el billete en USD')
                        ->body('Ingrese la denominación o monto en efectivo USD que entregó el cliente.')
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                $bill = round((float) $billRaw, 2);
                $documentTotal = (float) $sale->total;
                if ($bill + 0.0001 < $documentTotal) {
                    Notification::make()
                        ->title('El billete no cubre el total')
                        ->body('El efectivo del cliente debe ser mayor o igual al total cobrado ('.self::formatMoney($documentTotal).').')
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                $drawer = self::parseNonNegativeUsdAmount($data['cash_usd_drawer_out'] ?? null);
                $changeOnBillUsd = round($bill - $documentTotal, 2);
                if ($drawer > $changeOnBillUsd + 0.0001) {
                    Notification::make()
                        ->title('USD desde caja demasiado altos')
                        ->body('No puede retirar de la caja más dólares que el vuelto sobre el billete (máximo '.self::formatMoney($changeOnBillUsd).').')
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                $bcv = $sale->bcv_ves_per_usd !== null ? (float) $sale->bcv_ves_per_usd : 0.0;
                $meta = self::buildEfectivoUsdCajaMetaArray($bill, $documentTotal, $drawer, $bcv);
                $actor = $user->email ?? $user->name ?? 'sistema';

                try {
                    DB::transaction(function () use ($sale, $user, $meta, $actor, $bill, $documentTotal, $drawer, $bcv, $changeOnBillUsd): void {
                        if (SchemaFacade::hasColumn('sales', 'efectivo_usd_caja_meta')) {
                            $sale->efectivo_usd_caja_meta = $meta;
                            $sale->updated_by = $actor;
                            $sale->save();
                        }

                        if (! SchemaFacade::hasTable('cajas_fisicas_movimientos')) {
                            return;
                        }

                        if (PhysicalCashBoxMovement::query()->where('sale_id', $sale->id)->exists()) {
                            return;
                        }

                        $boxDefaults = [
                            'amount_usd' => 0,
                            'amount_ves' => 0,
                        ];
                        if (SchemaFacade::hasColumn('cajas_fisicas', 'is_open')) {
                            $boxDefaults['is_open'] = false;
                        }

                        $box = PhysicalCashBox::query()->firstOrCreate(
                            ['user_id' => $user->id],
                            $boxDefaults,
                        );

                        $finalUsd = (float) $meta['final_change_usd'];
                        $finalVes = $meta['final_change_ves'] !== null ? (float) $meta['final_change_ves'] : null;
                        $changeOnBillVes = $meta['change_on_bill_ves'] !== null ? (float) $meta['change_on_bill_ves'] : null;

                        PhysicalCashBoxMovement::query()->create([
                            'physical_cash_box_id' => $box->id,
                            'sale_id' => $sale->id,
                            'kind' => 'efectivo_usd_vuelto',
                            'client_bill_usd' => $bill,
                            'document_total_usd' => $documentTotal,
                            'change_on_bill_usd' => $changeOnBillUsd,
                            'change_on_bill_ves' => $changeOnBillVes,
                            'drawer_out_usd' => $drawer,
                            'final_change_usd' => $finalUsd,
                            'final_change_ves' => $finalVes,
                            'bcv_ves_per_usd' => $bcv > 0 ? round($bcv, 6) : null,
                            'meta' => $meta,
                            'created_by' => $actor,
                        ]);

                        self::applyEfectivoUsdMovementToPhysicalCashBox(
                            box: $box,
                            clientBillUsd: $bill,
                            drawerOutUsd: $drawer,
                            finalChangeVes: $finalVes,
                        );
                    });
                } catch (Throwable $e) {
                    Log::error('pos_efectivo_usd_caja_change_failed', [
                        'sale_id' => $sale->id,
                        'message' => $e->getMessage(),
                    ]);
                    Notification::make()
                        ->title('No se pudo guardar el movimiento de caja')
                        ->body('Intente de nuevo o registre el vuelto manualmente. '.$e->getMessage())
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                AuditLogger::record(
                    'pos_efectivo_usd_caja_change_recorded',
                    'Caja · Vueltos USD registrados en caja física · '.$sale->sale_number,
                    Sale::class,
                    $sale->id,
                    $sale->sale_number,
                    [
                        'module' => 'pos_caja',
                        'sale_id' => $sale->id,
                        'drawer_out_usd' => $drawer,
                    ],
                );

                Notification::make()
                    ->title('Movimiento de caja registrado')
                    ->body('Vuelto en USD/VES guardado para conciliación de caja física.')
                    ->success()
                    ->send();

                return $redirect();
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
     * @param  array<string, mixed>  $candidate
     */
    private static function storeBdvConciliationRecord(
        array $candidate,
        Response $response,
        string $environment,
        ?string $resolvedReference = null,
    ): void {
        $user = Auth::user();
        $branchId = $user?->branch_id;

        if (blank($branchId)) {
            return;
        }

        try {
            $responseData = $response->json();
            if (! is_array($responseData)) {
                $responseData = ['raw' => $response->body()];
            }

            $rawCode = $responseData['code'] ?? $responseData['codigo'] ?? null;
            $bdvCode = is_scalar($rawCode) ? (string) $rawCode : null;
            $bdvMessage = self::bdvConciliationFailureMessage($response);

            ConciliationBdv::query()->create([
                'branch_id' => (int) $branchId,
                'user_id' => $user?->id,
                'sale_id' => null,
                'environment' => $environment,
                'payer_document' => (string) ($candidate['cedulaPagador'] ?? ''),
                'payer_phone' => (string) ($candidate['telefonoPagador'] ?? ''),
                'destination_phone' => (string) ($candidate['telefonoDestino'] ?? ''),
                'reference' => $resolvedReference ?? self::extractBdvConciliationReference(
                    $response,
                    (string) ($candidate['referencia'] ?? ''),
                ),
                'payment_date' => (string) ($candidate['fechaPago'] ?? now()->toDateString()),
                'amount' => (float) ($candidate['importe'] ?? 0),
                'origin_bank' => (string) ($candidate['bancoOrigen'] ?? ''),
                'req_ced' => (bool) ($candidate['reqCed'] ?? false),
                'bdv_http_status' => $response->status(),
                'bdv_code' => $bdvCode,
                'bdv_message' => $bdvMessage,
                'bdv_payload' => $candidate,
                'bdv_response' => $responseData,
                'conciliated_at' => now(),
                'created_by' => $user?->email ?? $user?->name ?? 'sistema',
            ]);
        } catch (Throwable $e) {
            Log::error('bdv.pos_conciliation.persist_failed', [
                'message' => $e->getMessage(),
                'branch_id' => $branchId,
            ]);
        }
    }

    private static function findRecentSuccessfulBdvConciliation(int $branchId, string $reference, float $paymentVes): ?ConciliationBdv
    {
        if ($branchId <= 0) {
            return null;
        }

        $normalizedReference = preg_replace('/\D+/', '', trim($reference)) ?? '';
        $amount = round(max(0.0, $paymentVes), 2);
        $query = ConciliationBdv::query()
            ->where('branch_id', $branchId)
            ->where('amount', $amount)
            ->where('conciliated_at', '>=', now()->subMinutes(30))
            ->where(function (Builder $query): void {
                $query->where(function (Builder $ok): void {
                    $ok->where('bdv_http_status', 200)
                        ->where(function (Builder $codes): void {
                            $codes->whereIn('bdv_code', ['00', '01', '1000', '200'])
                                ->orWhereNull('bdv_code');
                        });
                })->orWhere('is_manual', true);
            });

        if ($normalizedReference !== '') {
            $query->where(function (Builder $query) use ($normalizedReference): void {
                $query->where('reference', $normalizedReference)
                    ->orWhere('reference', 'like', '%'.$normalizedReference)
                    ->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(bdv_response, '$.data.referencia')) LIKE ?",
                        ['%'.$normalizedReference],
                    );
            });
        }

        return $query
            ->orderByDesc('conciliated_at')
            ->first();
    }

    /**
     * Teléfono destino (comercio) para conciliación BDV en caja, tomado de la sucursal del usuario actual.
     */
    private static function resolveBdvCommercePhoneForPosConciliation(): string
    {
        $branchId = Auth::user()?->branch_id;
        if (blank($branchId)) {
            return '';
        }

        return trim((string) Branch::query()
            ->whereKey((int) $branchId)
            ->value('pm_conciliation_phone'));
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
                            'pay_with_cachea' => false,
                            'cachea_paid_amount' => null,
                            'cachea_complement_payment_method' => 'efectivo_usd',
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
                    'bdv_pm_manual_otp_requested' => false,
                    'bdv_pm_otp_code' => null,
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
                            ->default(false)
                            ->live(),
                        Hidden::make('bdv_pm_failure_message')
                            ->default(null),
                        Hidden::make('bdv_pm_manual_otp_requested')
                            ->default(false)
                            ->live(),
                        TextEntry::make('bdv_pm_failure_inline')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->html()
                            ->hidden(fn (Get $get): bool => ! self::bdvPmManualOtpUiVisible($get))
                            ->state(fn (Get $get): HtmlString => self::bdvPmFailureInlineHtml($get))
                            ->dehydrated(false)
                            ->extraEntryWrapperAttributes([
                                'class' => 'farmadoc-bdv-pm-inline-failure',
                            ]),
                        Placeholder::make('bdv_pm_manual_otp_help')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::bdvPmManualOtpUiVisible($get))
                            ->content(fn (Get $get): HtmlString => self::bdvPmManualOtpHelpHtml($get)),
                        Action::make('bdvPmRequestManualConciliation')
                            ->label(fn (Get $get): string => self::bdvPmManualOtpRequested($get)
                                ? 'Reenviar código OTP'
                                : 'Conciliar Manual')
                            ->icon(Heroicon::Key)
                            ->color('warning')
                            ->cancelParentActions(false)
                            ->visible(fn (Get $get): bool => self::bdvPmManualOtpUiVisible($get))
                            ->extraAttributes([
                                'class' => 'farmadoc-bdv-pm-inline-failure-action',
                            ])
                            ->action(function (Action $action, Get $get, Set $set): void {
                                self::requestPosManualConciliationOtp($action, $get, $set);
                            }),
                        OneTimeCodeInput::make('bdv_pm_otp_code')
                            ->label('Código OTP')
                            ->length(6)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::bdvPmManualOtpUiVisible($get))
                            ->helperText('Ingrese el código de 6 dígitos enviado al gerente. Es de un solo uso.'),
                        Action::make('bdvPmConfirmManualConciliation')
                            ->label('Confirmar conciliación manual')
                            ->icon(Heroicon::Check)
                            ->color('success')
                            ->cancelParentActions(false)
                            ->visible(fn (Get $get): bool => self::bdvPmManualOtpUiVisible($get))
                            ->extraAttributes([
                                'class' => 'farmadoc-bdv-pm-inline-failure-action',
                            ])
                            ->action(function (Action $action, Get $get): void {
                                self::confirmPosManualConciliation($action, $get);
                            }),
                        Action::make('bdvPmBackToCashRegister')
                            ->label('Regresar a caja y seleccionar otro método')
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->color('danger')
                            ->cancelParentActions(false)
                            ->visible(fn (Get $get): bool => self::bdvPmManualOtpUiVisible($get))
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
                                    'pay_with_cachea' => false,
                                    'cachea_paid_amount' => null,
                                    'cachea_complement_payment_method' => 'efectivo_usd',
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
                    $action->halt();

                    return;
                }

                $paymentVes = (float) ($action->getArguments()['payment_ves'] ?? 0);
                $importe = self::normalizeBdvImporteForPosWizard($data['bdv_pm_importe'] ?? null, $paymentVes);

                $telefonoComercio = self::resolveBdvCommercePhoneForPosConciliation();
                if ($telefonoComercio === '') {
                    Log::warning('bdv.pos_conciliation.config', [
                        'message' => 'Falta teléfono de conciliación BDV en la sucursal del cajero.',
                        'branch_id' => Auth::user()?->branch_id,
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        'La sucursal no tiene configurado el teléfono de conciliación Pago Móvil (BDV). Pídale a un administrador que lo registre en Sucursales.',
                        'Configuración incompleta',
                    );
                }

                $telefonoDestinoNorm = self::normalizeBdvTelefonoPagadorPayload($telefonoComercio);
                if ($telefonoDestinoNorm === '') {
                    Log::warning('bdv.pos_conciliation.config', [
                        'message' => 'Teléfono de conciliación BDV de sucursal no normalizable (telefonoDestino vacío).',
                        'branch_id' => Auth::user()?->branch_id,
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        'El teléfono de conciliación Pago Móvil (BDV) de la sucursal no tiene formato válido. Use 04XX… o 58… según manual BDV.',
                        'Configuración incompleta',
                    );
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

                Log::info('bdv.pos_conciliation.environment', [
                    'environment' => $environment,
                    'module' => 'pos_caja',
                    'app_env' => config('app.env'),
                ]);

                if ($environment === 'production' && blank(self::bdvProductionConciliationApiKey())) {
                    Log::error('bdv.pos_conciliation.config', [
                        'message' => 'Falta BDV_PRODUCTION_KEY_CONCILIATION o BDV_CONCILIATION_API_KEY.',
                        'module' => 'pos_caja',
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        'No está configurada la API Key de conciliación BDV en producción. Defina BDV_PRODUCTION_KEY_CONCILIATION en el servidor.',
                        'Configuración incompleta',
                    );
                }

                $response = null;

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
                } catch (Halt $exception) {
                    throw $exception;
                } catch (ValidationException $e) {
                    Log::warning('bdv.pos_conciliation.validation_failed', [
                        'environment' => $environment,
                        'errors' => $e->errors(),
                        'payload_redacted' => self::redactBdvConciliationPayloadForLog($candidate),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        collect($e->errors())->flatten()->implode(' '),
                        'Datos inválidos',
                    );
                } catch (InvalidArgumentException $e) {
                    Log::error('bdv.pos_conciliation.invalid_argument', [
                        'environment' => $environment,
                        'message' => $e->getMessage(),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        self::truncateForUserMessage($e->getMessage()),
                        'Conciliación no aceptada',
                    );
                } catch (ConnectionException $e) {
                    Log::error('bdv.pos_conciliation.connection', [
                        'environment' => $environment,
                        'message' => $e->getMessage(),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        'No hubo respuesta del banco: '.self::truncateForUserMessage($e->getMessage()).' Compruebe la red o reintente más tarde.',
                        'Sin conexión con BDV',
                    );
                } catch (RuntimeException $e) {
                    Log::error('bdv.pos_conciliation.runtime', [
                        'environment' => $environment,
                        'message' => $e->getMessage(),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        self::truncateForUserMessage($e->getMessage()),
                        'Conciliación no aceptada',
                    );
                } catch (Throwable $e) {
                    Log::error('bdv.pos_conciliation.exception', [
                        'environment' => $environment,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        'Ocurrió un error al conciliar: '.self::truncateForUserMessage($e->getMessage()),
                        'Conciliación fallida',
                    );
                }

                if ($response === null || ! self::isBdvConciliationSuccessful($response)) {
                    self::markBdvConciliationFailureInWizard(
                        $livewire,
                        $action,
                        $response !== null
                            ? self::formatBdvApiResponseForNotification($response)
                            : 'El banco no confirmó la conciliación del Pago Móvil.',
                        'Pago no conciliado por BDV',
                    );
                }

                if (! $response instanceof Response) {
                    $action->halt();

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
                $bdvReference = self::extractBdvConciliationReference(
                    $response,
                    (string) ($candidate['referencia'] ?? ''),
                );

                self::storeBdvConciliationRecord($candidate, $response, $environment, $bdvReference);

                self::attemptAutoRegisterSaleAfterPagoMovilReady($livewire, $bdvReference !== ''
                    ? $bdvReference
                    : self::extractBdvConciliationReference($response, (string) ($candidate['referencia'] ?? '')));
            });
    }

    /**
     * Guarda el fallo de conciliación en el wizard para mostrar alerta inline y el botón de conciliación manual.
     * Hace halt para que la modal de conciliación no se cierre.
     */
    private static function markBdvConciliationFailureInWizard(
        BasePage $livewire,
        Action $action,
        string $detailMessage,
        ?string $title = null,
    ): void {
        $message = trim($detailMessage);
        if ($title !== null && $title !== '') {
            $message = $title.'. '.$message;
        }

        $patch = [
            'bdv_pm_failed' => true,
            'bdv_pm_failure_message' => $message,
        ];

        self::patchMountedActionData($livewire, self::PAGO_MOVIL_CONCILIATION_ACTION_NAME, $patch);
        $action->data(array_merge($action->getData(), $patch), shouldMutate: false);

        AuditLogger::record(
            'pos_caja_bdv_conciliation_failed',
            'Caja · Conciliación Pago Móvil no aceptada',
            properties: [
                'module' => 'pos_caja',
                'detail' => Str::limit($message, 800),
            ],
        );

        $action->halt();
    }

    private static function haltPosPagoMovilConciliationIfMounted(Action $action): void
    {
        if ($action->getName() === self::PAGO_MOVIL_CONCILIATION_ACTION_NAME) {
            $action->halt();
        }
    }

    private static function requestPosManualConciliationOtp(Action $action, Get $get, Set $set): void
    {
        $livewire = $action->getLivewire();
        if (! $livewire instanceof BasePage) {
            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            Notification::make()
                ->title('Debe iniciar sesión.')
                ->danger()
                ->send();

            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        $branchId = (int) ($user->branch_id ?? 0);
        if ($branchId <= 0) {
            Notification::make()
                ->title('Sucursal requerida')
                ->body('El cajero no tiene sucursal asignada para conciliar de forma manual.')
                ->danger()
                ->send();

            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        $paymentVes = self::mountedPagoMovilConciliationPaymentVes($livewire);
        $candidate = self::posManualConciliationCandidateFromGet($get, $livewire, $paymentVes);

        try {
            $context = app(ManualBdvConciliationService::class)->otpContextFromForm([
                'branch_id' => $branchId,
                'reference' => $candidate['referencia'] ?? null,
                'amount' => $candidate['importe'] ?? null,
                'payer_document' => $candidate['cedulaPagador'] ?? null,
                'payer_phone' => $candidate['telefonoPagador'] ?? null,
                'destination_phone' => $candidate['telefonoDestino'] ?? null,
                'payment_date' => $candidate['fechaPago'] ?? null,
                'origin_bank' => $candidate['bancoOrigen'] ?? null,
            ]);

            app(ManualBdvConciliationOtpService::class)->issueForPosCashier($user, $branchId, $context);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo solicitar el OTP')
                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                ->danger()
                ->send();

            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        $set('bdv_pm_manual_otp_requested', true);
        self::patchMountedActionData($livewire, self::PAGO_MOVIL_CONCILIATION_ACTION_NAME, [
            'bdv_pm_failed' => true,
            'bdv_pm_manual_otp_requested' => true,
        ]);

        Notification::make()
            ->title('Código OTP enviado')
            ->body('Se envió un código de 6 dígitos por email y WhatsApp al gerente de la sucursal y a los administradores. Caduca en 10 minutos. Ingrese la clave en esta misma ventana.')
            ->success()
            ->send();

        self::haltPosPagoMovilConciliationIfMounted($action);
    }

    private static function confirmPosManualConciliation(Action $action, Get $get): void
    {
        $livewire = $action->getLivewire();
        if (! $livewire instanceof BasePage) {
            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            Notification::make()
                ->title('Debe iniciar sesión.')
                ->danger()
                ->send();

            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        $paymentVes = self::mountedPagoMovilConciliationPaymentVes($livewire);
        $candidate = self::posManualConciliationCandidateFromGet($get, $livewire, $paymentVes);
        $reference = preg_replace('/\D+/', '', (string) ($candidate['referencia'] ?? '')) ?? '';
        if ($reference === '') {
            Notification::make()
                ->title('Falta la referencia del Pago Móvil')
                ->body('Indique la referencia de 4 a 6 dígitos en esta ventana y vuelva a confirmar la conciliación manual.')
                ->danger()
                ->send();

            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        $candidate['referencia'] = $reference;
        $rawOtp = self::posManualConciliationWizardValue($get, $livewire, 'bdv_pm_otp_code');
        if (is_array($rawOtp)) {
            $otpCode = implode('', array_map(static fn (mixed $digit): string => (string) $digit, $rawOtp));
        } else {
            $otpCode = $rawOtp !== null && $rawOtp !== '' ? (string) $rawOtp : null;
        }

        try {
            $record = app(ManualBdvConciliationService::class)->registerFromPos($user, $candidate, $otpCode);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo conciliar de forma manual')
                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                ->danger()
                ->send();

            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        $reference = self::referenceFromConciliationBdv($record);
        if ($reference === '') {
            $reference = preg_replace('/\D+/', '', (string) ($candidate['referencia'] ?? '')) ?? '';
        }

        if ($reference === '') {
            Notification::make()
                ->title('Pago conciliado')
                ->body('La conciliación quedó registrada, pero falta la referencia para crear la venta. Indique la referencia de 4 a 6 dígitos y pulse de nuevo «Confirmar conciliación manual».')
                ->warning()
                ->send();

            self::haltPosPagoMovilConciliationIfMounted($action);

            return;
        }

        self::attemptAutoRegisterSaleAfterPagoMovilReady($livewire, $reference);
    }

    /**
     * @return array{
     *     cedulaPagador: string,
     *     telefonoPagador: string,
     *     telefonoDestino: string,
     *     referencia: string,
     *     fechaPago: string,
     *     importe: string,
     *     bancoOrigen: string,
     *     reqCed: bool
     * }
     */
    private static function posManualConciliationCandidateFromGet(Get $get, BasePage $livewire, float $paymentVes): array
    {
        $telefonoComercio = self::resolveBdvCommercePhoneForPosConciliation();
        $telefonoDestinoNorm = self::normalizeBdvTelefonoPagadorPayload($telefonoComercio);
        $referencia = preg_replace('/\D+/', '', (string) (self::posManualConciliationWizardValue($get, $livewire, 'bdv_pm_referencia') ?? '')) ?? '';

        return [
            'cedulaPagador' => self::normalizeBdvCedulaPagadorPayload(trim((string) (self::posManualConciliationWizardValue($get, $livewire, 'bdv_pm_cedula_pagador') ?? ''))),
            'telefonoPagador' => self::normalizeBdvTelefonoPagadorPayload((string) (self::posManualConciliationWizardValue($get, $livewire, 'bdv_pm_telefono_pagador') ?? '')),
            'telefonoDestino' => $telefonoDestinoNorm,
            'referencia' => $referencia,
            'fechaPago' => self::formatDateForBdvConciliationCandidate(self::posManualConciliationWizardValue($get, $livewire, 'bdv_pm_fecha_pago')),
            'importe' => self::normalizeBdvImporteForPosWizard(self::posManualConciliationWizardValue($get, $livewire, 'bdv_pm_importe'), $paymentVes),
            'bancoOrigen' => trim((string) (self::posManualConciliationWizardValue($get, $livewire, 'bdv_pm_banco_origen') ?? '')),
            'reqCed' => filter_var((self::posManualConciliationWizardValue($get, $livewire, 'bdv_pm_req_ced') ?? '0') === '1', FILTER_VALIDATE_BOOL),
        ];
    }

    private static function posManualConciliationWizardValue(Get $get, BasePage $livewire, string $field): mixed
    {
        $fromGet = $get($field);
        if (filled($fromGet) || $fromGet === '0' || $fromGet === 0 || $fromGet === false) {
            return $fromGet;
        }

        $mounted = $livewire->mountedActions ?? null;
        if (! is_array($mounted)) {
            return $fromGet;
        }

        $fallback = $fromGet;
        foreach ($mounted as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            if (! (filled($value) || $value === '0' || $value === 0 || $value === false)) {
                continue;
            }

            if (($entry['name'] ?? '') === self::PAGO_MOVIL_CONCILIATION_ACTION_NAME) {
                return $value;
            }

            $fallback = $value;
        }

        return $fallback;
    }

    private static function mountedPagoMovilConciliationPaymentVes(BasePage $livewire): float
    {
        $mounted = $livewire->mountedActions ?? null;
        if (! is_array($mounted)) {
            return 0.0;
        }

        foreach ($mounted as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['name'] ?? '') !== self::PAGO_MOVIL_CONCILIATION_ACTION_NAME) {
                continue;
            }

            $arguments = is_array($entry['arguments'] ?? null) ? $entry['arguments'] : [];

            return (float) ($arguments['payment_ves'] ?? 0);
        }

        return 0.0;
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

    private static function bdvPmManualOtpRequested(Get $get): bool
    {
        return filter_var($get('bdv_pm_manual_otp_requested') ?? false, FILTER_VALIDATE_BOOL);
    }

    private static function bdvPmManualOtpUiVisible(Get $get): bool
    {
        return filter_var($get('bdv_pm_failed') ?? false, FILTER_VALIDATE_BOOL)
            || self::bdvPmManualOtpRequested($get);
    }

    private static function bdvPmManualOtpHelpHtml(Get $get): HtmlString
    {
        if (self::bdvPmManualOtpRequested($get)) {
            return new HtmlString(
                '<div class="rounded-xl border border-warning-500/40 bg-warning-500/10 p-3 text-sm text-warning-700 dark:text-warning-200">'
                .'<p class="font-medium">Clave OTP enviada</p>'
                .'<p class="mt-1">Se envió un código de 6 dígitos por email y WhatsApp al gerente de la sucursal y a los administradores. Ingrese la clave abajo y pulse «Confirmar conciliación manual». Caduca en 10 minutos.</p>'
                .'</div>'
            );
        }

        return new HtmlString(
            '<div class="rounded-xl border border-warning-500/40 bg-warning-500/10 p-3 text-sm text-warning-700 dark:text-warning-200">'
            .'<p class="font-medium">Conciliación manual</p>'
            .'<p class="mt-1">Pulse «Conciliar Manual» para enviar el código al gerente. Luego ingrese la clave de 6 dígitos y confirme para registrar la venta.</p>'
            .'</div>'
        );
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
            .'<p class="farmadoc-bdv-pm-inline-alert__hint">Abajo puede conciliar de forma manual: solicite el OTP al gerente, ingrese la clave y confirme para registrar la venta. O vuelva a la caja para otro método de pago.</p>'
            .'</div>'
        );
    }

    /**
     * Referencia canónica devuelta por BDV (p. ej. 12 dígitos). El cajero suele ingresar solo 4–6 dígitos.
     */
    private static function extractBdvConciliationReference(Response $response, string $fallback): string
    {
        $decoded = $response->json();
        if (is_array($decoded)) {
            foreach (['data.referencia', 'data.reference', 'referencia', 'reference'] as $path) {
                $value = data_get($decoded, $path);
                if (is_scalar($value)) {
                    $normalized = preg_replace('/\D+/', '', (string) $value) ?? '';
                    if ($normalized !== '') {
                        return $normalized;
                    }
                }
            }
        }

        return preg_replace('/\D+/', '', trim($fallback)) ?? '';
    }

    private static function referenceFromConciliationBdv(ConciliationBdv $record): string
    {
        foreach ([
            data_get($record->bdv_response, 'data.referencia'),
            data_get($record->bdv_payload, 'referencia'),
            $record->reference,
        ] as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $normalized = preg_replace('/\D+/', '', (string) $candidate) ?? '';
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private static function resolvePagoMovilPaymentReference(
        int $branchId,
        float $paymentVes,
        string $partialReference = '',
        ?ConciliationBdv $knownRecord = null,
    ): string {
        $record = $knownRecord
            ?? self::findRecentSuccessfulBdvConciliation($branchId, $partialReference, $paymentVes);

        if ($record instanceof ConciliationBdv) {
            return self::referenceFromConciliationBdv($record);
        }

        return preg_replace('/\D+/', '', trim($partialReference)) ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function currentPosRegisterMountedFormData(BasePage $livewire): array
    {
        $mounted = $livewire->mountedActions ?? null;
        if (! is_array($mounted)) {
            return [];
        }

        foreach ($mounted as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['name'] ?? '') !== self::REGISTER_ACTION_NAME) {
                continue;
            }

            return is_array($entry['data'] ?? null) ? $entry['data'] : [];
        }

        return [];
    }

    /**
     * Tras conciliación PM lista (BDV OK o manual con OTP): actualiza la caja, cierra el modal y dispara «Registrar venta».
     */
    private static function attemptAutoRegisterSaleAfterPagoMovilReady(
        BasePage $livewire,
        string $reference,
    ): void {
        $reference = preg_replace('/\D+/', '', trim($reference)) ?? '';
        if ($reference === '') {
            Notification::make()
                ->title('Falta la referencia del Pago Móvil')
                ->body('La conciliación está lista, pero no hay referencia para registrar la venta. Indique la referencia de 4 a 6 dígitos y confirme de nuevo.')
                ->danger()
                ->send();

            return;
        }

        $registerData = array_merge(self::currentPosRegisterMountedFormData($livewire), [
            'reference' => $reference,
            'bdv_pm_conciliated' => true,
        ]);

        $lineItems = collect($registerData['line_items'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['product_id'] ?? null))
            ->count();

        if ($lineItems === 0) {
            Log::error('pos.sale_register.auto_register_empty_cart', [
                'module' => 'pos_caja',
                'reference_suffix' => strlen($reference) >= 4 ? substr($reference, -4) : $reference,
            ]);

            Notification::make()
                ->title('Pago conciliado')
                ->body('No se pudo registrar la venta: el carrito quedó vacío al cerrar la conciliación. Pulse «Registrar venta» en la caja.')
                ->warning()
                ->persistent()
                ->send();

            $livewire->unmountAction();

            return;
        }

        self::posSaleRegisterTrace('bdv_ok_auto_register_start', [
            'reference_suffix' => strlen($reference) >= 4
                ? substr($reference, -4)
                : $reference,
            'bdv_pm_conciliated' => true,
            'line_items_count' => $lineItems,
        ]);

        // Cierra solo el modal de conciliación PM (la caja sigue en el stack).
        $livewire->unmountAction();

        self::posSaleRegisterTrace('bdv_ok_pm_modal_unmounted', []);

        /*
         * Igual que venta a crédito: replaceMountedAction con pos_data re-ejecuta mountUsing
         * y hace fill() del formulario con referencia BDV + carrito antes de callMountedAction().
         */
        $livewire->replaceMountedAction(self::REGISTER_ACTION_NAME, [
            'pos_data' => $registerData,
            'client_id' => $registerData['client_id'] ?? null,
        ]);

        try {
            $livewire->callMountedAction();
            self::posSaleRegisterTrace('bdv_ok_auto_register_dispatched', []);
        } catch (Throwable $e) {
            Log::error('pos.sale_register.auto_register_failed', [
                'module' => 'pos_caja',
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Pago conciliado')
                ->body('No se pudo registrar la venta automáticamente. Pulse «Registrar venta» en la caja.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function posSaleRegisterTrace(string $step, array $context = []): void
    {
        if (! self::posSaleRegisterDebugEnabled()) {
            return;
        }

        Log::info('pos.sale_register.trace', array_merge([
            'step' => $step,
            'module' => 'pos_caja',
        ], $context));
    }

    private static function posSaleRegisterDebugEnabled(): bool
    {
        if (app()->isLocal()) {
            return true;
        }

        return filter_var(config('bdv_conciliation.pos_debug_sale_register'), FILTER_VALIDATE_BOOL);
    }

    private static function posSaleBlockLoggingEnabled(): bool
    {
        return filter_var(config('bdv_conciliation.pos_log_sale_blocks'), FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function logPosSaleBlocked(string $reason, array $context = []): void
    {
        self::posSaleRegisterTrace('register_blocked', array_merge(['reason' => $reason], $context));

        if (! self::posSaleBlockLoggingEnabled()) {
            return;
        }

        Log::warning('pos.sale_register.blocked', array_merge([
            'reason' => $reason,
            'module' => 'pos_caja',
        ], $context));
    }

    private static function bdvProductionConciliationApiKey(): ?string
    {
        $key = config('bdv_conciliation.environments.production.keys.conciliation');

        if (! is_string($key)) {
            return null;
        }

        $trimmed = trim($key);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private static function patchPosRegisterMountedData(BasePage $livewire, array $patch): bool
    {
        return self::patchMountedActionData($livewire, self::REGISTER_ACTION_NAME, $patch);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private static function patchMountedActionData(BasePage $livewire, string $actionName, array $patch): bool
    {
        $mounted = $livewire->mountedActions ?? null;
        if (! is_array($mounted) || $mounted === []) {
            return false;
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

            return true;
        }

        return false;
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

    private static function posLineItemsAreEmpty(mixed $lineItems): bool
    {
        if (! is_array($lineItems) || $lineItems === []) {
            return true;
        }

        foreach ($lineItems as $row) {
            if (is_array($row) && filled($row['product_id'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function posCartProductCellHtml(Get $get): HtmlString
    {
        $productId = $get('product_id');
        if (! filled($productId)) {
            return new HtmlString('<span class="farmadoc-pos-cart-product__code">Sin producto</span>');
        }

        $product = self::posProduct((int) $productId);
        $code = $product instanceof Product && filled($product->barcode)
            ? (string) $product->barcode
            : '—';
        $name = $product instanceof Product
            ? (string) $product->name
            : 'Producto #'.(int) $productId;

        $badge = '';
        $branchId = Auth::user()?->branch_id;
        if ($product instanceof Product && filled($branchId)) {
            $alert = self::posNearExpiryLotAlert((int) $branchId, (int) $product->id);
            if ($alert instanceof NearExpiryLotAlert) {
                $badge = $alert->badgeHtml();
            }
        }

        return new HtmlString(
            '<div class="farmadoc-pos-cart-product">'
            .'<span class="farmadoc-pos-cart-product__code">'.e($code).'</span>'
            .'<span class="farmadoc-pos-cart-product__name">'.e($name).$badge.'</span>'
            .'</div>'
        );
    }

    private static function posCartLineTotalHtml(Get $get): HtmlString
    {
        $rowState = [
            'product_id' => $get('product_id'),
            'quantity' => $get('quantity'),
        ];
        $original = self::computeLineTotalFromRowState($rowState, $get);
        $clientId = filled($get('../../client_id'))
            ? (int) $get('../../client_id')
            : (filled($get('../client_id')) ? (int) $get('../client_id') : null);
        $discountPercent = app(ClientCommercialDiscountResolver::class)->percentForClientId($clientId);
        $usdAmount = $original;
        $usdHtml = '<span class="farmadoc-pos-line-total">'.e(self::formatMoney($original)).'</span>';

        if ($discountPercent > 0.00001) {
            $usdAmount = round($original * (1 - ($discountPercent / 100)), 2);
            $usdHtml = '<span class="line-through opacity-70">'.e(self::formatMoney($original)).'</span>'
                .' <span class="farmadoc-pos-line-total">'.e(self::formatMoney($usdAmount)).'</span>';
        }

        return new HtmlString(
            '<div class="farmadoc-pos-line-total-stack">'
            .$usdHtml
            .'<span class="farmadoc-pos-line-total-ves">'.e(self::posUsdToVesLabel($usdAmount, $get)).'</span>'
            .'</div>'
        );
    }

    private static function posUsdToVesLabel(float $usdAmount, Get $get): string
    {
        $rate = self::effectiveVesUsdRateForPosProductLabels($get);
        if ($rate <= 0.0) {
            return 'Bs. —';
        }

        return self::formatBolivaresReferenceFromVes(self::posListPriceVesFromUsd($usdAmount, $rate));
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
     * Referencia en bolívares para el precio de lista final en USD, respetando la precisión completa de la tasa BCV.
     */
    private static function posListPriceVesFromUsd(float $usdUnitFinal, float $vesPerUsd): float
    {
        if ($vesPerUsd <= 0.0) {
            return 0.0;
        }

        return $usdUnitFinal * $vesPerUsd;
    }

    /**
     * Etiqueta de opción en el buscador POS sin cargar modelos (solo datos ya resueltos en SQL + tasa precalculada).
     */
    private static function formatPosSearchOptionLabelFast(string $base, float $saleUsdFinal, float $branchQty, float $rate): string
    {
        $label = $base.' · '.self::formatMoney($saleUsdFinal);
        if ($rate > 0.0) {
            $label .= ' · '.self::formatBolivaresReferenceFromVes(self::posListPriceVesFromUsd($saleUsdFinal, $rate));
        } else {
            $label .= ' · Bs. —';
        }
        if ($branchQty <= 0.0001) {
            $label .= ' · Cant. 0';
        } else {
            $label .= ' · Cant. '.InventoryQuantityFormat::display($branchQty);
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
            $unitPricing['unit_final'],
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

            foreach (['../ves_usd_rate', '../../ves_usd_rate', '../ves_usd_rate_manual', '../../ves_usd_rate_manual'] as $path) {
                $value = $get($path);
                if (is_numeric($value) && (float) $value > 0) {
                    return (float) $value;
                }
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
        $usd = $unitPricing['unit_final'];
        $segments = [];
        $rate = self::effectiveVesUsdRateForPosProductLabels($get);
        if ($rate > 0.0) {
            $segments[] = self::formatBolivaresReferenceFromVes(
                self::posListPriceVesFromUsd($usd, $rate),
            );
        }

        $inv = self::posBranchInventory($branchId, (int) $product->id);
        $qty = $inv instanceof Inventory ? max(0.0, (float) $inv->quantity) : 0.0;
        if ($qty <= 0.0001) {
            $segments[] = 'Cant. 0';
        } else {
            $segments[] = 'Cant. '.InventoryQuantityFormat::display($qty);
        }

        return $segments === [] ? '' : (' · '.implode(' · ', $segments));
    }

    /**
     * Monto USD estrictamente positivo o null.
     */
    private static function parseStrictPositiveUsdAmount(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $n = (float) $value;
        if ($n <= 0.0) {
            return null;
        }

        return $n;
    }

    private static function parseNonNegativeUsdAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (! is_numeric($value)) {
            return 0.0;
        }

        return max(0.0, (float) $value);
    }

    private static function truthyFormBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function postSaleEfectivoUsdSnapshotTotal(Get $get): float
    {
        return (float) ($get('efd_snapshot_total') ?? 0);
    }

    private static function postSaleEfectivoUsdSnapshotBcv(Get $get): float
    {
        return (float) ($get('efd_snapshot_bcv') ?? 0);
    }

    private static function postSaleEfectivoUsdChangeOnBillUsd(Get $get): ?float
    {
        $bill = self::parseStrictPositiveUsdAmount($get('cash_usd_client_bill'));
        if ($bill === null) {
            return null;
        }

        return round($bill - self::postSaleEfectivoUsdSnapshotTotal($get), 2);
    }

    private static function postSaleEfectivoUsdFinalChangeUsd(Get $get): ?float
    {
        $onBill = self::postSaleEfectivoUsdChangeOnBillUsd($get);
        if ($onBill === null) {
            return null;
        }

        $drawer = self::parseNonNegativeUsdAmount($get('cash_usd_drawer_out'));

        return round(max(0.0, $onBill - $drawer), 2);
    }

    private static function formatPostSaleEfectivoUsdChangeOnBillUsdLabel(Get $get): string
    {
        $usd = self::postSaleEfectivoUsdChangeOnBillUsd($get);
        if ($usd === null) {
            return '—';
        }

        return self::formatMoney($usd);
    }

    private static function formatPostSaleEfectivoUsdChangeOnBillVesLabel(Get $get): string
    {
        $rate = self::postSaleEfectivoUsdSnapshotBcv($get);
        if ($rate <= 0.0) {
            return 'Bs. —';
        }

        $usd = self::postSaleEfectivoUsdChangeOnBillUsd($get);
        if ($usd === null) {
            return 'Bs. —';
        }

        return self::formatBolivaresReferenceFromVes($usd * $rate);
    }

    private static function formatPostSaleEfectivoUsdFinalChangeUsdLabel(Get $get): string
    {
        $usd = self::postSaleEfectivoUsdFinalChangeUsd($get);
        if ($usd === null) {
            return '—';
        }

        return self::formatMoney($usd);
    }

    private static function formatPostSaleEfectivoUsdFinalChangeVesLabel(Get $get): string
    {
        $rate = self::postSaleEfectivoUsdSnapshotBcv($get);
        if ($rate <= 0.0) {
            return 'Bs. —';
        }

        $usd = self::postSaleEfectivoUsdFinalChangeUsd($get);
        if ($usd === null) {
            return 'Bs. —';
        }

        return self::formatBolivaresReferenceFromVes($usd * $rate);
    }

    private static function postSaleEfectivoUsdDrawerOutHelperText(Get $get): string
    {
        $onBill = self::postSaleEfectivoUsdChangeOnBillUsd($get);
        if ($onBill === null) {
            return 'Indique primero el billete. El máximo a retirar de la caja es el vuelto en USD sobre ese billete.';
        }

        if ($onBill < 0) {
            return 'El billete indicado no cubre el total cobrado en esta venta.';
        }

        return 'Máximo desde la caja: '.self::formatMoney($onBill).' (vuelto en USD sobre el billete).';
    }

    /**
     * @return array{
     *     client_bill_usd: float,
     *     document_total_usd: float,
     *     change_on_bill_usd: float,
     *     change_on_bill_ves: float|null,
     *     bcv_ves_per_usd: float|null,
     *     drawer_out_usd: float,
     *     final_change_usd: float,
     *     final_change_ves: float|null,
     * }
     */
    private static function buildEfectivoUsdCajaMetaArray(
        float $clientBillUsd,
        float $documentTotalUsd,
        float $drawerOutUsd,
        float $vesUsdRate,
    ): array {
        $changeOnBill = round($clientBillUsd - $documentTotalUsd, 2);
        $rate = max(0.0, $vesUsdRate);
        $finalUsd = round(max(0.0, $changeOnBill - $drawerOutUsd), 2);

        return [
            'client_bill_usd' => round($clientBillUsd, 2),
            'document_total_usd' => round($documentTotalUsd, 2),
            'change_on_bill_usd' => $changeOnBill,
            'change_on_bill_ves' => $rate > 0.0 ? round($changeOnBill * $rate, 2) : null,
            'bcv_ves_per_usd' => $rate > 0.0 ? round($rate, 6) : null,
            'drawer_out_usd' => round($drawerOutUsd, 2),
            'final_change_usd' => $finalUsd,
            'final_change_ves' => $rate > 0.0 ? round($finalUsd * $rate, 2) : null,
        ];
    }

    /**
     * Aplica el efecto del movimiento sobre la caja física del cajero:
     * - USD: entra el billete del cliente y sale lo retirado en USD para vuelto.
     * - VES: sale el vuelto restante entregado en bolívares.
     */
    private static function applyEfectivoUsdMovementToPhysicalCashBox(
        PhysicalCashBox $box,
        float $clientBillUsd,
        float $drawerOutUsd,
        ?float $finalChangeVes,
    ): void {
        $usdDelta = round(max(0.0, $clientBillUsd) - max(0.0, $drawerOutUsd), 2);
        $vesDelta = -1 * round(max(0.0, $finalChangeVes ?? 0.0), 2);

        $box->forceFill([
            'amount_usd' => round((float) $box->amount_usd + $usdDelta, 2),
            'amount_ves' => round((float) $box->amount_ves + $vesDelta, 2),
        ])->save();
    }

    /**
     * Registra en caja física el neto en bolívares recibido en efectivo (pago mixto).
     *
     * @param  array<string, mixed>  $data
     */
    private static function recordMixedEfectivoVesPhysicalCashBoxMovementIfNeeded(
        Sale $sale,
        User $user,
        array $data,
        string $paymentMethod,
        string $actor,
    ): void {
        if ($paymentMethod !== 'mixed'
            && ! ($paymentMethod === PosPaymentMethodOptions::CACHEA && CacheaPosPaymentSupport::complementMethodFromData($data) === 'mixed')) {
            return;
        }

        if (! SchemaFacade::hasTable('cajas_fisicas_movimientos')) {
            return;
        }

        $documentTotalUsd = $paymentMethod === PosPaymentMethodOptions::CACHEA
            ? CacheaPosPaymentSupport::paidAmountFromData($data)
            : (float) $sale->total;
        $vesUsdRate = $sale->bcv_ves_per_usd !== null ? (float) $sale->bcv_ves_per_usd : 0.0;
        $cashLines = MixedPosPaymentSupport::efectivoVesCashLines($data, $documentTotalUsd, $vesUsdRate);

        if ($cashLines === []) {
            return;
        }

        if (PhysicalCashBoxMovement::query()->where('sale_id', $sale->id)->exists()) {
            return;
        }

        $totalDue = 0.0;
        $totalReceived = 0.0;
        $totalChange = 0.0;
        $linesMeta = [];

        foreach ($cashLines as $line) {
            $due = round((float) $line['amount_due'], 2);
            $received = round((float) $line['cash_received'], 2);
            $change = round(max(0.0, $received - $due), 2);
            $totalDue += $due;
            $totalReceived += $received;
            $totalChange += $change;
            $linesMeta[] = [
                'slot' => $line['slot'],
                'amount_due' => $due,
                'cash_received' => $received,
                'change_ves' => $change,
            ];
        }

        $totalDue = round($totalDue, 2);
        $totalReceived = round($totalReceived, 2);
        $totalChange = round($totalChange, 2);
        $netVes = round($totalReceived - $totalChange, 2);

        if ($netVes <= 0.00001) {
            return;
        }

        $boxDefaults = [
            'amount_usd' => 0,
            'amount_ves' => 0,
        ];
        if (SchemaFacade::hasColumn('cajas_fisicas', 'is_open')) {
            $boxDefaults['is_open'] = false;
        }

        $box = PhysicalCashBox::query()->firstOrCreate(
            ['user_id' => $user->id],
            $boxDefaults,
        );

        $bcv = $sale->bcv_ves_per_usd !== null ? (float) $sale->bcv_ves_per_usd : null;

        $meta = [
            'payment_method' => 'mixed',
            'mixed_mode' => MixedPosPaymentSupport::resolveMode($data),
            'ves_cash_lines' => $linesMeta,
            'ves_cash_received_total' => $totalReceived,
            'ves_payment_due_total' => $totalDue,
            'change_ves_total' => $totalChange,
            'net_ves_to_drawer' => $netVes,
            'payment_usd' => (float) $sale->payment_usd,
            'payment_ves' => (float) $sale->payment_ves,
        ];

        PhysicalCashBoxMovement::query()->create([
            'physical_cash_box_id' => $box->id,
            'sale_id' => $sale->id,
            'kind' => self::MIXED_EFECTIVO_VES_VUELTO_KIND,
            'client_bill_usd' => 0,
            'document_total_usd' => round($documentTotalUsd, 2),
            'change_on_bill_usd' => 0,
            'change_on_bill_ves' => $totalChange,
            'drawer_out_usd' => 0,
            'final_change_usd' => 0,
            'final_change_ves' => $totalChange,
            'bcv_ves_per_usd' => $bcv !== null && $bcv > 0 ? round($bcv, 6) : null,
            'meta' => $meta,
            'created_by' => $actor,
        ]);

        self::applyEfectivoVesNetToPhysicalCashBox($box, $netVes);

        AuditLogger::record(
            'pos_mixed_efectivo_ves_caja_change_recorded',
            'Caja · Efectivo VES (pago mixto) registrado en caja física · '.$sale->sale_number,
            Sale::class,
            $sale->id,
            $sale->sale_number,
            [
                'module' => 'pos_caja',
                'sale_id' => $sale->id,
                'net_ves_to_drawer' => $netVes,
                'change_ves_total' => $totalChange,
                'ves_cash_received_total' => $totalReceived,
            ],
        );
    }

    private static function applyEfectivoVesNetToPhysicalCashBox(PhysicalCashBox $box, float $netVes): void
    {
        $vesDelta = round(max(0.0, $netVes), 2);

        $box->forceFill([
            'amount_ves' => round((float) $box->amount_ves + $vesDelta, 2),
        ])->save();
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
            'transfer_ves', 'pago_movil', 'efectivo_ves', 'punto_venta_ves', 'mixed', 'credito_cliente', 'cachea' => false,
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
     *     discount_percent: float,
     *     document_total: float,
     *     ves_tax_fraction: float,
     *     per_line: list<array{line_subtotal: float, tax_amount: float, line_total: float}>,
     * }
     */
    private static function finalizePosPricingFromValidLines(
        array $lines,
        string $paymentMethod,
        float $discountRequested = 0.0,
        float $discountPercent = 0.0,
    ): array {
        if ($lines === []) {
            return [
                'subtotal' => 0.0,
                'tax_total' => 0.0,
                'igtf_total' => 0.0,
                'discount_total' => 0.0,
                'discount_percent' => 0.0,
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

        $discountPercent = max(0.0, min(100.0, $discountPercent));
        $discountTotal = $discountPercent > 0.00001
            ? app(ClientCommercialDiscountResolver::class)->amountFromSubtotal($subtotal, $discountPercent)
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
            'discount_percent' => $discountPercent,
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
        $rate = self::effectiveVesUsdRate($get);

        if ($paymentMethod === PosPaymentMethodOptions::CACHEA) {
            $cacheaData = [
                'cachea_paid_amount' => $get('cachea_paid_amount'),
                'cachea_complement_payment_method' => $get('cachea_complement_payment_method'),
                ...self::mixedFormDataFromGet($get),
            ];
            $breakdown = CacheaPosPaymentSupport::breakdown($total, $cacheaData, $rate);

            return [
                'payment_usd' => $breakdown['payment_usd'],
                'payment_ves' => ($breakdown['complement_payment_method'] ?? '') === 'mixed'
                    ? $breakdown['payment_ves']
                    : $breakdown['payment_ves_equivalent'],
            ];
        }

        if ($paymentMethod === 'mixed') {
            $breakdown = MixedPosPaymentSupport::breakdown($total, $rate, self::mixedFormDataFromGet($get));

            return [
                'payment_usd' => (float) ($breakdown['payment_usd'] ?? 0.0),
                'payment_ves' => (float) ($breakdown['payment_ves'] ?? 0.0),
            ];
        }

        $mixedUsdPaid = (float) ($get('mixed_usd_paid') ?? 0);
        [$paymentUsd, $paymentVes] = self::resolvePaymentAmounts($total, $paymentMethod, $mixedUsdPaid, $rate);

        return [
            'payment_usd' => $paymentUsd,
            'payment_ves' => $paymentVes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mixedFormDataFromGet(Get $get): array
    {
        return [
            'mixed_use_usd_portion' => $get('mixed_use_usd_portion'),
            'mixed_use_ves_portion' => $get('mixed_use_ves_portion'),
            'mixed_usd_paid' => $get('mixed_usd_paid'),
            'mixed_ves_payment_method' => $get('mixed_ves_payment_method'),
            'mixed_ves_cash_received' => $get('mixed_ves_cash_received'),
            'mixed_ves_split_method_1' => $get('mixed_ves_split_method_1'),
            'mixed_ves_split_method_2' => $get('mixed_ves_split_method_2'),
            'mixed_ves_split_amount_1' => $get('mixed_ves_split_amount_1'),
            'mixed_ves_split_cash_received_1' => $get('mixed_ves_split_cash_received_1'),
            'mixed_ves_split_cash_received_2' => $get('mixed_ves_split_cash_received_2'),
        ];
    }

    private static function isCacheaMixedInitialFromGet(Get $get): bool
    {
        return filter_var($get('pay_with_cachea') ?? false, FILTER_VALIDATE_BOOLEAN)
            && (string) ($get('cachea_complement_payment_method') ?? '') === 'mixed';
    }

    private static function showsMixedPaymentFields(Get $get): bool
    {
        return ($get('payment_method') ?? '') === 'mixed' || self::isCacheaMixedInitialFromGet($get);
    }

    private static function mixedDocumentUsdFromGet(Get $get): float
    {
        if (self::isCacheaMixedInitialFromGet($get)) {
            return CacheaPosPaymentSupport::paidAmountFromGet($get);
        }

        return self::computeSaleTotal($get);
    }

    private static function isMixedUsdModeFromGet(Get $get): bool
    {
        return self::showsMixedPaymentFields($get)
            && MixedPosPaymentSupport::isUsdMode(self::mixedFormDataFromGet($get));
    }

    private static function isMixedVesModeFromGet(Get $get): bool
    {
        return self::showsMixedPaymentFields($get)
            && MixedPosPaymentSupport::isVesMode(self::mixedFormDataFromGet($get));
    }

    private static function mixedVesSplitSecondAmountFromGet(Get $get): float
    {
        $totalVes = self::computePaymentBreakdownForForm($get)['payment_ves'];
        $first = round((float) ($get('mixed_ves_split_amount_1') ?? 0), 2);

        return max(0.0, round($totalVes - $first, 2));
    }

    private static function mixedPaymentUsesPagoMovilFromGet(Get $get): bool
    {
        if (! self::showsMixedPaymentFields($get)) {
            return false;
        }

        return MixedPosPaymentSupport::vesPortionUsesPagoMovil(
            self::mixedFormDataFromGet($get),
            self::mixedDocumentUsdFromGet($get),
            self::effectiveVesUsdRate($get),
        );
    }

    private static function mixedPaymentUsesPointOfSaleFromGet(Get $get): bool
    {
        if (! self::showsMixedPaymentFields($get)) {
            return false;
        }

        return MixedPosPaymentSupport::vesPortionUsesPointOfSale(
            self::mixedFormDataFromGet($get),
            self::mixedDocumentUsdFromGet($get),
            self::effectiveVesUsdRate($get),
        );
    }

    private static function formUsesPointOfSale(Get $get): bool
    {
        if ($get('payment_method') === 'punto_venta_ves') {
            return true;
        }

        if ($get('payment_method') === PosPaymentMethodOptions::CACHEA) {
            return CacheaPosPaymentSupport::usesPointOfSaleComplement(
                PosPaymentMethodOptions::CACHEA,
                [
                    'cachea_paid_amount' => $get('cachea_paid_amount'),
                    'cachea_complement_payment_method' => $get('cachea_complement_payment_method'),
                    ...self::mixedFormDataFromGet($get),
                ],
                self::computeSaleTotal($get),
                self::effectiveVesUsdRate($get),
            );
        }

        if ($get('payment_method') !== 'mixed') {
            return false;
        }

        return self::mixedPaymentUsesPointOfSaleFromGet($get);
    }

    private static function mountMixedPagoMovilConciliationFromGet(Get $get, Component $component): void
    {
        if (! self::mixedPaymentUsesPagoMovilFromGet($get)) {
            return;
        }

        $livewire = $component->getLivewire();
        if (! $livewire instanceof HasActions) {
            return;
        }

        $paymentVes = MixedPosPaymentSupport::pagoMovilVesAmount(
            self::mixedFormDataFromGet($get),
            self::mixedDocumentUsdFromGet($get),
            self::effectiveVesUsdRate($get),
        );
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

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolvePosSaleNotes(
        string $paymentMethod,
        bool $generateAccountsReceivable,
        array $data,
        float $documentTotal,
        float $vesUsdRate,
    ): ?string {
        if ($generateAccountsReceivable && $paymentMethod === 'credito_cliente') {
            return 'Venta a crédito · cuenta por cobrar registrada desde caja.';
        }

        if ($paymentMethod === 'mixed') {
            return MixedPosPaymentSupport::buildSaleNotesSuffix($data, $documentTotal, $vesUsdRate);
        }

        if ($paymentMethod === PosPaymentMethodOptions::CACHEA
            && CacheaPosPaymentSupport::complementMethodFromData($data) === 'mixed') {
            return MixedPosPaymentSupport::buildSaleNotesSuffix(
                $data,
                CacheaPosPaymentSupport::paidAmountFromData($data),
                $vesUsdRate,
            );
        }

        return null;
    }

    private static function selectedMixedVesPaymentMethodFromGet(Get $get): string
    {
        return MixedPosPaymentSupport::selectedUsdModeVesMethod(self::mixedFormDataFromGet($get));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function selectedMixedVesPaymentMethodFromData(array $data): string
    {
        return MixedPosPaymentSupport::selectedUsdModeVesMethod($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function usesPagoMovilForVesPortion(
        string $paymentMethod,
        string $mixedVesPaymentMethod,
        array $data = [],
        float $documentTotal = 0.0,
        float $vesUsdRate = 0.0,
    ): bool {
        if ($paymentMethod === 'pago_movil') {
            return true;
        }

        if ($paymentMethod === PosPaymentMethodOptions::CACHEA
            && CacheaPosPaymentSupport::complementMethodFromData($data) === 'mixed') {
            return MixedPosPaymentSupport::vesPortionUsesPagoMovil(
                $data,
                CacheaPosPaymentSupport::paidAmountFromData($data),
                $vesUsdRate,
            );
        }

        if ($paymentMethod !== 'mixed') {
            return false;
        }

        return MixedPosPaymentSupport::vesPortionUsesPagoMovil($data, $documentTotal, $vesUsdRate);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function usesPointOfSaleForVesPortion(
        string $paymentMethod,
        string $mixedVesPaymentMethod,
        array $data = [],
        float $documentTotal = 0.0,
        float $vesUsdRate = 0.0,
    ): bool {
        if ($paymentMethod === 'punto_venta_ves') {
            return true;
        }

        if ($paymentMethod === PosPaymentMethodOptions::CACHEA
            && CacheaPosPaymentSupport::complementMethodFromData($data) === 'mixed') {
            return MixedPosPaymentSupport::vesPortionUsesPointOfSale(
                $data,
                CacheaPosPaymentSupport::paidAmountFromData($data),
                $vesUsdRate,
            );
        }

        if ($paymentMethod !== 'mixed') {
            return false;
        }

        return MixedPosPaymentSupport::vesPortionUsesPointOfSale($data, $documentTotal, $vesUsdRate);
    }

    /**
     * @return (
     *     array{
     *         subtotal: float,
     *         tax_total: float,
     *         igtf_total: float,
     *         discount_total: float,
     *         discount_percent: float,
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
        $clientId = filled($get('client_id')) ? (int) $get('client_id') : null;
        $discountPercent = app(ClientCommercialDiscountResolver::class)->percentForClientId($clientId);

        return self::finalizePosPricingFromValidLines(
            $valid,
            $paymentMethod,
            discountPercent: $discountPercent,
        );
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
                'active_ingredient',
            ];
            if (SchemaFacade::hasColumn('products', 'requires_expiry_on_purchase')) {
                $select[] = 'requires_expiry_on_purchase';
            }
            if (SchemaFacade::hasColumn('products', 'express_branch_prices')) {
                $select[] = 'express_branch_prices';
            }
            if (SchemaFacade::hasColumn('products', 'direct_price')) {
                $select[] = 'direct_price';
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

        self::warmPosFefoAlertsForBranch($branchId, $ids);
    }

    /**
     * @param  list<int>  $productIds
     */
    private static function warmPosFefoAlertsForBranch(int $branchId, array $productIds): void
    {
        if ($branchId <= 0 || $productIds === [] || ! config('inventory.fefo_pos_alerts_enabled', true)) {
            return;
        }

        $cacheKey = 'cash_register.fefo_alerts.'.$branchId;
        /** @var array<int, NearExpiryLotAlert|null> $alertMap */
        $alertMap = request()->attributes->get($cacheKey, []);
        if (! is_array($alertMap)) {
            $alertMap = [];
        }

        $missing = array_values(array_diff($productIds, array_keys($alertMap)));
        if ($missing === []) {
            return;
        }

        $fetched = app(FefoLotBalanceQueryService::class)->nearExpiryAlertsForProducts($branchId, $missing);

        foreach ($missing as $pid) {
            $alertMap[$pid] = $fetched[$pid] ?? null;
        }

        request()->attributes->set($cacheKey, $alertMap);
    }

    private static function posNearExpiryLotAlert(int $branchId, int $productId): ?NearExpiryLotAlert
    {
        if ($branchId <= 0 || $productId <= 0) {
            return null;
        }

        self::warmPosFefoAlertsForBranch($branchId, [$productId]);

        $cacheKey = 'cash_register.fefo_alerts.'.$branchId;
        /** @var array<int, NearExpiryLotAlert|null>|null $map */
        $map = request()->attributes->get($cacheKey);

        if (! is_array($map) || ! array_key_exists($productId, $map)) {
            return null;
        }

        $alert = $map[$productId];

        return $alert instanceof NearExpiryLotAlert ? $alert : null;
    }

    private static function notifyPosNearExpiryLotIfNeeded(int $branchId, int $productId, string $productLabel): void
    {
        $alert = self::posNearExpiryLotAlert($branchId, $productId);
        if (! $alert instanceof NearExpiryLotAlert) {
            return;
        }

        $notification = Notification::make()
            ->title($alert->notificationTitle())
            ->body($alert->notificationBodyHtml($productLabel));

        if ($alert->isCritical()) {
            $notification->danger()->persistent();
        } else {
            $notification->warning()->persistent();
        }

        $notification->send();

        $user = Auth::user();
        $product = self::posProduct($productId);
        if ($user instanceof User && $product instanceof Product) {
            PosFefoAlertLogRegistrar::register($branchId, $user, $product, $alert);
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

    private static function notifyPosStockZero(int $branchId, int $productId, string $productLabel): void
    {
        Notification::make()
            ->title('Producto sin existencia')
            ->body('El producto "'.$productLabel.'" tiene existencia 0. Escanee otro producto para continuar.')
            ->warning()
            ->send();

        self::registerPosStockFailureAfterNotification($branchId, $productId);
    }

    private static function registerPosStockFailureAfterNotification(int $branchId, int $productId): void
    {
        $user = Auth::user();
        if (! $user instanceof User || $branchId <= 0 || $productId <= 0) {
            return;
        }

        $product = self::posProduct($productId);
        if (! $product instanceof Product) {
            $product = Product::query()->find($productId);
        }

        if (! $product instanceof Product) {
            return;
        }

        $available = self::posAvailableQuantity($branchId, $productId);
        $quantity = $available !== null ? max(0.0, $available) : 0.0;

        PosInventoryStockFailureRegistrar::register($branchId, $product, $user, $quantity);
    }

    private static function notifyPosQuantityExceedsStock(string $productLabel, float $requestedQuantity, float $availableQuantity): void
    {
        Notification::make()
            ->title('Cantidad mayor a la existencia')
            ->body(
                'Para "'.$productLabel.'" solicitó '.InventoryQuantityFormat::displayDot($requestedQuantity).
                ' y solo hay '.InventoryQuantityFormat::displayDot($availableQuantity).' disponibles.'
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
        $rate = self::effectiveVesUsdRateForPosProductLabels($get);
        $rows = self::consultProductSearchRows(
            $branchId,
            $search,
            $rate,
            $requirePositiveQuantity,
            25,
        );

        return collect($rows)->mapWithKeys(static fn (array $row): array => [
            $row['id'] => self::formatPosSearchOptionLabelFast(
                $row['base_label'],
                $row['price_usd_raw'],
                $row['quantity_raw'],
                $rate,
            ),
        ])->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     active_ingredient: string,
     *     price_usd: string,
     *     price_ves: string,
     *     quantity: string,
     *     out_of_stock: bool,
     *     base_label: string,
     *     price_usd_raw: float,
     *     quantity_raw: float
     * }>
     */
    public static function searchPosConsultProductsForCurrentUser(string $search, LivewireComponent $livewire): array
    {
        if (! PhysicalCashBoxBillingGate::userMayUseCashRegister(Auth::user())) {
            return [];
        }

        $branchId = Auth::user()?->branch_id;
        if (blank($branchId)) {
            return [];
        }

        $rate = 0.0;
        if ($livewire instanceof BasePage) {
            foreach ($livewire->mountedActions ?? [] as $entry) {
                if (! is_array($entry) || ($entry['name'] ?? '') !== self::REGISTER_ACTION_NAME) {
                    continue;
                }

                if (is_array($entry['data'] ?? null)) {
                    $rate = self::effectiveVesUsdRateFromData($entry['data']);
                }

                break;
            }
        }

        if ($rate <= 0.0) {
            $rate = self::effectiveVesUsdRateForPosProductLabels(null);
        }

        $rows = self::consultProductSearchRows((int) $branchId, $search, $rate);

        return array_map(static fn (array $row): array => [
            'id' => $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'active_ingredient' => $row['active_ingredient'],
            'price_usd' => $row['price_usd'],
            'price_ves' => $row['price_ves'],
            'quantity' => $row['quantity'],
            'out_of_stock' => $row['out_of_stock'],
        ], $rows);
    }

    private static function normalizePosConsultSearchTerm(string $search): string
    {
        $term = trim($search);
        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        return $term;
    }

    private static function posConsultTermLooksLikeCode(string $term): bool
    {
        $compact = preg_replace('/[\s\-]/', '', $term) ?? $term;

        return $compact !== '' && ctype_digit($compact) && strlen($compact) >= 8;
    }

    private static function resolvePosConsultExactProductId(int $branchId, string $term, bool $requirePositiveQuantity): ?int
    {
        $candidates = array_values(array_unique(array_filter([
            $term,
            preg_replace('/[\s\-]/', '', $term) ?: null,
        ], static fn (mixed $value): bool => is_string($value) && $value !== '')));

        foreach ($candidates as $candidate) {
            $id = self::resolvePosProductIdByExactCatalogCode($candidate);
            if ($id === null && SchemaFacade::hasColumn('products', 'sku') && ctype_digit($candidate)) {
                $id = Product::query()
                    ->where('is_active', true)
                    ->where('sku', 'CSV-'.$candidate)
                    ->value('id');
                $id = filled($id) ? (int) $id : null;
            }

            if ($id === null) {
                continue;
            }

            if ($requirePositiveQuantity) {
                $qty = (float) (DB::table('inventories')
                    ->where('branch_id', $branchId)
                    ->where('product_id', $id)
                    ->value('quantity') ?? 0);
                if ($qty <= 0.0001) {
                    continue;
                }
            }

            return $id;
        }

        foreach ($candidates as $candidate) {
            $id = DB::table('inventories')
                ->join('products', 'products.id', '=', 'inventories.product_id')
                ->where('inventories.branch_id', $branchId)
                ->where('products.is_active', true)
                ->whereNotNull('inventories.product_id')
                ->when($requirePositiveQuantity, fn ($q) => $q->where('inventories.quantity', '>', 0))
                ->where(function ($w) use ($candidate): void {
                    $w->where('products.barcode', $candidate)
                        ->orWhereRaw('REPLACE(REPLACE(products.barcode, " ", ""), "-", "") = ?', [$candidate]);

                    if (SchemaFacade::hasColumn('products', 'sku')) {
                        $w->orWhere('products.sku', $candidate)
                            ->orWhere('products.sku', 'CSV-'.$candidate);
                    }
                })
                ->value('products.id');

            if (filled($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    public static function addConsultProductToMountedRegister(LivewireComponent $livewire, int $productId): void
    {
        if (! $livewire instanceof BasePage || $productId <= 0) {
            return;
        }

        if (! PhysicalCashBoxBillingGate::userMayUseCashRegister(Auth::user())) {
            return;
        }

        $branchId = Auth::user()?->branch_id;
        if (blank($branchId)) {
            return;
        }

        $mounted = $livewire->mountedActions ?? null;
        if (! is_array($mounted) || $mounted === []) {
            return;
        }

        foreach ($mounted as $i => $entry) {
            if (! is_array($entry) || ($entry['name'] ?? '') !== self::REGISTER_ACTION_NAME) {
                continue;
            }

            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            $lineItems = is_array($data['line_items'] ?? null) ? $data['line_items'] : [];
            $mounted[$i]['data']['line_items'] = self::appendProductToLineItemsArray(
                (int) $branchId,
                $productId,
                $lineItems,
            );
            $livewire->mountedActions = $mounted;

            return;
        }
    }

    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     active_ingredient: string,
     *     price_usd: string,
     *     price_ves: string,
     *     quantity: string,
     *     out_of_stock: bool,
     *     base_label: string,
     *     price_usd_raw: float,
     *     quantity_raw: float
     * }>
     */
    private static function consultProductSearchRows(
        int $branchId,
        string $search,
        float $vesUsdRate,
        bool $requirePositiveQuantity = false,
        int $emptyLimit = 10,
        int $searchLimit = 40,
    ): array {
        $term = self::normalizePosConsultSearchTerm($search);

        if ($term !== '') {
            $exactProductId = self::resolvePosConsultExactProductId($branchId, $term, $requirePositiveQuantity);

            if (filled($exactProductId)) {
                $mapped = self::mapConsultProductRows(
                    $branchId,
                    collect([(object) ['id' => (int) $exactProductId]]),
                    $vesUsdRate,
                    $requirePositiveQuantity,
                );

                if ($mapped !== []) {
                    return $mapped;
                }
            }

            if (self::posConsultTermLooksLikeCode($term)) {
                return [];
            }
        }

        $query = DB::table('inventories')
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->where('inventories.branch_id', $branchId)
            ->where('products.is_active', true)
            ->whereNotNull('inventories.product_id')
            ->when($requirePositiveQuantity, fn ($q) => $q->where('inventories.quantity', '>', 0))
            ->select(['products.id'])
            ->orderBy('products.name')
            ->limit($term === '' ? max(10, $emptyLimit) : $searchLimit);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $ingredientLike = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
            $compact = mb_strtolower(preg_replace('/\s+/u', '', $term) ?? $term);
            $compactLike = '%'.addcslashes($compact, '%_\\').'%';
            $query->where(function ($w) use ($like, $ingredientLike, $compactLike): void {
                $w->where('products.name', 'like', $like)
                    ->orWhereRaw('REPLACE(LOWER(products.name), " ", "") LIKE ?', [$compactLike])
                    ->orWhere('products.barcode', 'like', $like)
                    ->orWhereRaw('LOWER(products.active_ingredient) LIKE ?', [$ingredientLike])
                    ->orWhereRaw('REPLACE(LOWER(products.active_ingredient), " ", "") LIKE ?', [$compactLike]);

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

        return self::mapConsultProductRows($branchId, $rows, $vesUsdRate, $requirePositiveQuantity);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     active_ingredient: string,
     *     price_usd: string,
     *     price_ves: string,
     *     quantity: string,
     *     out_of_stock: bool,
     *     base_label: string,
     *     price_usd_raw: float,
     *     quantity_raw: float
     * }>
     */
    private static function mapConsultProductRows(int $branchId, Collection $rows, float $vesUsdRate, bool $requirePositiveQuantity): array
    {
        $ids = $rows
            ->map(static fn (mixed $row): int => (int) ($row->id ?? 0))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        self::warmPosDataForBranch($branchId, $ids);

        $mapped = [];

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }

            $product = self::posProduct($id);
            if (! $product instanceof Product) {
                continue;
            }

            $unitPricing = self::posUnitPricingForBranch($product, $branchId);
            $unitFinal = $unitPricing['unit_final'];
            $available = self::posAvailableQuantity($branchId, $id);
            $qty = $available !== null ? max(0.0, $available) : 0.0;

            if ($requirePositiveQuantity && $qty <= 0.0001) {
                continue;
            }

            $barcode = filled($product->barcode) ? (string) $product->barcode : '';
            $sku = filled($product->sku) ? (string) $product->sku : '';
            $code = $barcode !== '' ? $barcode : ($sku !== '' ? $sku : '—');
            $name = (string) $product->name;
            $base = $barcode !== '' ? $barcode.' · '.$name : $name;

            $mapped[] = [
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'active_ingredient' => self::formatPosActiveIngredient($product->active_ingredient),
                'price_usd' => self::formatMoney($unitFinal),
                'price_ves' => $vesUsdRate > 0.0
                    ? self::formatBolivaresReferenceFromVes(self::posListPriceVesFromUsd($unitFinal, $vesUsdRate))
                    : 'Bs. —',
                'quantity' => InventoryQuantityFormat::display($qty),
                'out_of_stock' => $qty <= 0.0001,
                'base_label' => $base,
                'price_usd_raw' => $unitFinal,
                'quantity_raw' => $qty,
            ];
        }

        return $mapped;
    }

    private static function formatPosActiveIngredient(mixed $value): string
    {
        if (is_string($value) && $value !== '' && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_string($item) && $item !== '') {
                    $parts[] = $item;
                }
            }

            return $parts === [] ? '—' : implode(', ', $parts);
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return '—';
    }

    /**
     * @return array{unit_net: float, unit_final: float, applies_vat: bool}
     */
    private static function posUnitPricingForBranch(Product $product, int $branchId): array
    {
        $inventory = $branchId > 0
            ? self::posBranchInventory($branchId, (int) $product->id)
            : null;

        return ProductUnitPricingForBranch::resolve($product, $branchId, $inventory);
    }

    /**
     * @param  array<string, mixed>  $lineItems
     * @return array<string, mixed>
     */
    private static function appendProductToLineItemsArray(int $branchId, int $productId, array $lineItems): array
    {
        self::warmPosDataForBranch($branchId, [$productId]);

        $product = self::posProduct($productId);
        $productLabel = $product instanceof Product
            ? $product->name
            : 'Producto #'.$productId;

        $available = self::posAvailableQuantity($branchId, $productId);
        if ($available !== null && $available <= 0.0001) {
            self::notifyPosStockZero($branchId, $productId, $productLabel);

            return self::pruneEmptyPosLineItems($lineItems);
        }

        self::notifyPosNearExpiryLotIfNeeded($branchId, $productId, $productLabel);

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

                return self::pruneEmptyPosLineItems($lineItems);
            }

            $lineItems[$key]['quantity'] = $nextQuantity;

            return self::pruneEmptyPosLineItems($lineItems);
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
        }

        $lineItems[$targetKey] = [
            'product_id' => $productId,
            'quantity' => 1,
        ];

        return self::pruneEmptyPosLineItems($lineItems);
    }

    /**
     * @param  array<string, mixed>  $lineItems
     * @return array<string, mixed>
     */
    private static function pruneEmptyPosLineItems(array $lineItems): array
    {
        $kept = [];
        foreach ($lineItems as $key => $row) {
            if (is_array($row) && filled($row['product_id'] ?? null)) {
                $kept[$key] = $row;
            }
        }

        return $kept;
    }

    /**
     * Cliente en la modal de caja: búsqueda, resumen o registro rápido.
     *
     * @return array<int, Component|\Filament\Forms\Components\Component>
     */
    private static function posRegisterClientSchema(): array
    {
        return [
            Hidden::make('client_id'),
            Select::make('pos_client_picker')
                ->label('Cliente')
                ->placeholder('Nombre o documento de identidad…')
                ->helperText('Vacío = mostrador. Busque un cliente o regístrelo abajo si no existe.')
                ->extraAttributes([
                    'class' => 'farmadoc-pos-register-client-select',
                ])
                ->visible(fn (Get $get): bool => blank($get('client_id')))
                ->dehydrated(false)
                ->live()
                ->searchable()
                ->searchDebounce(100)
                ->nullable()
                ->getSearchResultsUsing(fn (string $search): array => self::posClientSearchResults($search))
                ->getOptionLabelUsing(fn ($value): ?string => self::posClientOptionLabel($value))
                ->afterStateUpdated(function (mixed $state, Set $set): void {
                    self::clearPosQuickClientFields($set);

                    if (blank($state)) {
                        $set('client_id', null);
                        self::recordPosWalkIn();

                        return;
                    }

                    $set('client_id', (int) $state);
                    self::recordPosClientPickedFromCatalog((int) $state, 'busqueda_caja');
                })
                ->native(false)
                ->prefixIcon(Heroicon::User)
                ->columnSpanFull(),
            Grid::make([
                'default' => 1,
                'sm' => 12,
            ])
                ->visible(fn (Get $get): bool => filled($get('client_id')))
                ->extraAttributes([
                    'class' => 'farmadoc-pos-client-selected-row items-center gap-2',
                ])
                ->schema([
                    TextEntry::make('pos_client_summary')
                        ->label('Cliente')
                        ->state(fn (Get $get): string => self::posClientSummaryLabel($get('client_id')))
                        ->weight(FontWeight::SemiBold)
                        ->size(TextSize::Large)
                        ->dehydrated(false)
                        ->icon(Heroicon::User)
                        ->iconColor('primary')
                        ->extraAttributes([
                            'class' => 'rounded-xl bg-primary-500/10 px-3 py-2 text-primary-700 dark:bg-primary-500/15 dark:text-primary-100',
                        ])
                        ->columnSpan(['default' => 1, 'sm' => 11]),
                    SchemaActions::make([
                        Action::make('clearPosClient')
                            ->label('Cambiar cliente')
                            ->icon(Heroicon::PencilSquare)
                            ->iconButton()
                            ->color('gray')
                            ->tooltip('Elegir otro cliente')
                            ->action(function (Set $set, $livewire): void {
                                self::clearPosClientSelection($set, $livewire);
                            }),
                    ])
                        ->alignment(Alignment::End)
                        ->columnSpan(['default' => 1, 'sm' => 1]),
                ])
                ->columnSpanFull(),
            Section::make('Cliente nuevo')
                ->description('Si no aparece en el buscador, complete los datos y pulse «Registrar cliente».')
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
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    TextInput::make('quick_client_document')
                        ->label('Cédula / documento')
                        ->maxLength(120)
                        ->dehydrated(false)
                        ->columnSpan(['default' => 1, 'sm' => 1]),
                    TextInput::make('quick_client_phone')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(120)
                        ->dehydrated(false)
                        ->suffixAction(
                            Action::make('registerQuickClientFromPos')
                                ->label('Registrar cliente')
                                ->icon(Heroicon::UserPlus)
                                ->color('primary')
                                ->action(function (Get $get, Set $set): void {
                                    self::applyQuickClientRegistrationInRegister($get, $set);
                                }),
                            isInline: false,
                        )
                        ->columnSpan(['default' => 1, 'sm' => 1]),
                ])
                ->columns(['default' => 1, 'sm' => 2])
                ->columnSpanFull(),
        ];
    }

    private static function posClientSummaryLabel(mixed $clientId): string
    {
        if (! filled($clientId)) {
            return 'Mostrador / sin cliente';
        }

        $client = Client::query()
            ->select(['id', 'name', 'document_number'])
            ->find((int) $clientId);

        if (! $client instanceof Client) {
            return 'Cliente #'.(int) $clientId;
        }

        $doc = filled($client->document_number)
            ? ' · Doc. '.$client->document_number
            : '';

        $discount = app(ClientCommercialDiscountResolver::class)->resolve((int) $clientId);
        $discountLabel = filled($discount['label'] ?? null)
            ? ' · Desc. '.$discount['label']
            : '';

        return $client->name.$doc.$discountLabel;
    }

    private static function clearPosQuickClientFields(Set $set): void
    {
        $set('quick_client_name', null);
        $set('quick_client_document', null);
        $set('quick_client_phone', null);
    }

    private static function clearPosClientSelection(Set $set, mixed $livewire = null): void
    {
        $set('client_id', null);
        $set('pos_client_picker', null);
        self::clearPosQuickClientFields($set);

        if ($livewire instanceof LivewireComponent) {
            $livewire->js(self::mountPosRegisterFocusClientSelectJs());
        }
    }

    private static function recordPosClientPickedFromCatalog(int $clientId, string $via): void
    {
        $pickedLabel = Client::query()->whereKey($clientId)->value('name');
        AuditLogger::record(
            'pos_caja_client_picked_from_catalog',
            'Caja · Cliente elegido desde catálogo',
            Client::class,
            $clientId,
            filled($pickedLabel) ? (string) $pickedLabel : null,
            ['module' => 'pos_caja', 'via' => $via],
        );
    }

    private static function recordPosWalkIn(): void
    {
        AuditLogger::record(
            'pos_caja_walk_in',
            'Caja · Venta mostrador (sin cliente)',
            properties: ['module' => 'pos_caja'],
        );
    }

    private static function applyQuickClientRegistrationInRegister(Get $get, Set $set): void
    {
        if (filled($get('client_id'))) {
            return;
        }

        $quickName = trim((string) ($get('quick_client_name') ?? ''));
        $quickDoc = trim((string) ($get('quick_client_document') ?? ''));
        $quickPhone = trim((string) ($get('quick_client_phone') ?? ''));

        $anyQuick = $quickName !== '' || $quickDoc !== '' || $quickPhone !== '';
        if (! $anyQuick) {
            Notification::make()
                ->title('Datos incompletos')
                ->body('Indique nombre, cédula y teléfono para registrar el cliente, o deje el buscador vacío para mostrador.')
                ->warning()
                ->send();

            return;
        }

        if ($quickName === '' || $quickDoc === '' || $quickPhone === '') {
            Notification::make()
                ->title('Datos incompletos')
                ->body('Complete los tres campos (nombre, cédula y teléfono) para registrar el cliente nuevo.')
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
                ->body('Ya existe un cliente con ese documento; se usará ese registro en la venta.')
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
            $set('client_id', $existing->id);
            self::clearPosQuickClientFields($set);

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
            ->body('Puede continuar cargando productos en la venta.')
            ->success()
            ->send();
        $set('client_id', $client->id);
        self::clearPosQuickClientFields($set);
    }

    /**
     * Estado inicial del formulario de caja (modal).
     *
     * @return array<string, mixed>
     */
    /**
     * Campos Cashea pueden perderse al deshidratar controles deshabilitados u ocultos;
     * normaliza flags y recupera montos desde el formulario montado en Livewire.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    /**
     * En Pago Móvil el campo «Referencia de pago» de caja está oculto: la referencia vive en la modal BDV
     * y llega por pos_data / raw data. Sin este merge la venta se bloquea con «Falta la referencia».
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function enrichPosRegisterPagoMovilData(array $data, Action $action): array
    {
        $fromArgs = is_array($action->getArguments()['pos_data'] ?? null)
            ? $action->getArguments()['pos_data']
            : [];
        $raw = $action->getRawData();
        $rawArray = $raw instanceof Arrayable
            ? $raw->toArray()
            : (is_array($raw) ? $raw : []);

        $reference = self::firstFilledPaymentReference([
            $data['reference'] ?? null,
            $rawArray['reference'] ?? null,
            $fromArgs['reference'] ?? null,
        ]);
        if ($reference !== '') {
            $data['reference'] = $reference;
        }

        $data['bdv_pm_conciliated'] = filter_var($data['bdv_pm_conciliated'] ?? false, FILTER_VALIDATE_BOOL)
            || filter_var($rawArray['bdv_pm_conciliated'] ?? false, FILTER_VALIDATE_BOOL)
            || filter_var($fromArgs['bdv_pm_conciliated'] ?? false, FILTER_VALIDATE_BOOL);

        return $data;
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private static function firstFilledPaymentReference(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function findLatestSuccessfulBdvConciliationForCashier(int $branchId): ?ConciliationBdv
    {
        if ($branchId <= 0) {
            return null;
        }

        $userId = Auth::id();
        $query = ConciliationBdv::query()
            ->where('branch_id', $branchId)
            ->where('conciliated_at', '>=', now()->subMinutes(10))
            ->where(function (Builder $query): void {
                $query->where(function (Builder $ok): void {
                    $ok->where('bdv_http_status', 200)
                        ->where(function (Builder $codes): void {
                            $codes->whereIn('bdv_code', ['00', '01', '1000', '200'])
                                ->orWhereNull('bdv_code');
                        });
                })->orWhere('is_manual', true);
            })
            ->orderByDesc('conciliated_at');

        if (filled($userId)) {
            $query->where('user_id', (int) $userId);
        }

        return $query->first();
    }

    private static function enrichPosRegisterCacheaData(array $data, Action $action): array
    {
        $payWithCachea = filter_var($data['pay_with_cachea'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($payWithCachea) {
            $data['payment_method'] = PosPaymentMethodOptions::CACHEA;
        }

        if (! $payWithCachea && PosPaymentMethodOptions::isCachea($data['payment_method'] ?? null)) {
            $data['pay_with_cachea'] = true;
        }

        if (! CacheaPosPaymentSupport::isCacheaPayment(
            (string) ($data['payment_method'] ?? ''),
            $data['pay_with_cachea'] ?? false,
        )) {
            return $data;
        }

        $livewire = $action->getLivewire();
        if (! $livewire instanceof BasePage) {
            return $data;
        }

        $mounted = self::currentPosRegisterMountedFormData($livewire);

        if (blank($data['cachea_paid_amount'] ?? null) && filled($mounted['cachea_paid_amount'] ?? null)) {
            $data['cachea_paid_amount'] = $mounted['cachea_paid_amount'];
        }

        if (blank($data['cachea_complement_payment_method'] ?? null) && filled($mounted['cachea_complement_payment_method'] ?? null)) {
            $data['cachea_complement_payment_method'] = $mounted['cachea_complement_payment_method'];
        }

        if (blank($data['pay_with_cachea'] ?? null) && filter_var($mounted['pay_with_cachea'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $data['pay_with_cachea'] = true;
            $data['payment_method'] = PosPaymentMethodOptions::CACHEA;
        }

        return $data;
    }

    /**
     * El buscador de cliente no se deshidrata al ocultarse; el ID vive en client_id (Hidden).
     *
     * @param  array<string, mixed>  $data
     */
    private static function resolvePosClientIdFromRegisterData(array $data, ?Action $action = null): ?int
    {
        if (filled($data['client_id'] ?? null)) {
            return (int) $data['client_id'];
        }

        if (! $action instanceof Action) {
            return null;
        }

        $livewire = $action->getLivewire();
        if (! $livewire instanceof BasePage) {
            return null;
        }

        $mounted = self::currentPosRegisterMountedFormData($livewire);
        if (filled($mounted['client_id'] ?? null)) {
            return (int) $mounted['client_id'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function posFormSnapshotFromGet(Get $get): array
    {
        return [
            'reference' => trim((string) ($get('reference') ?? '')),
            'client_id' => filled($get('client_id')) ? (int) $get('client_id') : null,
            'generate_accounts_receivable' => false,
            'pay_with_cachea' => true,
            'payment_method' => PosPaymentMethodOptions::CACHEA,
            'cachea_paid_amount' => $get('cachea_paid_amount'),
            'cachea_complement_payment_method' => $get('cachea_complement_payment_method'),
            'bdv_pm_conciliated' => $get('bdv_pm_conciliated'),
        ];
    }

    private static function posRequiresPaymentReference(Get $get): bool
    {
        $method = (string) ($get('payment_method') ?? '');

        if (PosPaymentMethodOptions::requiresPaymentReference($method)) {
            return true;
        }

        if ($method === PosPaymentMethodOptions::CACHEA) {
            return CacheaPosPaymentSupport::complementRequiresReference(
                $method,
                [
                    'cachea_paid_amount' => $get('cachea_paid_amount'),
                    'cachea_complement_payment_method' => $get('cachea_complement_payment_method'),
                    ...self::mixedFormDataFromGet($get),
                ],
                self::computeSaleTotal($get),
                self::effectiveVesUsdRate($get),
            );
        }

        if ($method !== 'mixed') {
            return false;
        }

        return MixedPosPaymentSupport::requiresPaymentReference(
            self::mixedFormDataFromGet($get),
            self::computeSaleTotal($get),
            self::effectiveVesUsdRate($get),
        );
    }

    private static function defaultPosFormState(): array
    {
        return array_merge([
            'client_id' => null,
            'pos_client_picker' => null,
            'quick_client_name' => null,
            'quick_client_document' => null,
            'quick_client_phone' => null,
            'payment_method' => 'punto_venta_ves',
            'mixed_use_usd_portion' => true,
            'mixed_use_ves_portion' => false,
            'mixed_ves_payment_method' => 'punto_venta_ves',
            'mixed_ves_cash_received' => null,
            'mixed_ves_split_method_1' => 'punto_venta_ves',
            'mixed_ves_split_method_2' => 'transfer_ves',
            'mixed_ves_split_amount_1' => null,
            'mixed_ves_split_cash_received_1' => null,
            'mixed_ves_split_cash_received_2' => null,
            'generate_accounts_receivable' => false,
            'pay_with_cachea' => false,
            'cachea_paid_amount' => null,
            'cachea_complement_payment_method' => 'efectivo_usd',
            'bdv_pm_conciliated' => false,
            'pos_terminal_id' => null,
            'card_last4' => null,
            'mixed_usd_paid' => null,
            'reference' => null,
            'discount_total' => 0.0,
            'line_items' => [],
        ], self::initialDolarFormState());
    }

    /**
     * @return array{client_id: int|null, pos_data: array{line_items: list<array{product_id: int|null, quantity: float|int}>}}|null
     */
    public static function prefillArgsFromSaleTransferId(int $transferId): ?array
    {
        $transfer = self::posSaleTransferBaseQuery()
            ->whereKey($transferId)
            ->first();

        if (! $transfer instanceof ProductTransfer) {
            return null;
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
            return null;
        }

        return [
            'client_id' => filled($transfer->client_id) ? (int) $transfer->client_id : null,
            'pos_data' => [
                'line_items' => $rows,
            ],
        ];
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

    private static function focusPosConsultButtonJs(): string
    {
        return <<<'JS'
            setTimeout(() => {
                document.querySelector('.farmadoc-pos-consult__open')?.focus?.();
            }, 180);
            JS;
    }

    /**
     * Al abrir la caja sin cliente: enfoca el buscador de cliente en la sección Venta.
     */
    private static function mountPosRegisterFocusClientSelectJs(): string
    {
        return <<<'JS'
            setTimeout(() => {
                const wrap = document.querySelector('.farmadoc-pos-register-client-select');
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
        $lines[] = 'Precio original '.self::formatMoney($p['subtotal']);

        if ($p['discount_total'] > 0.00001) {
            $percentLabel = ($p['discount_percent'] ?? 0) > 0.00001
                ? ' ('.rtrim(rtrim(number_format((float) $p['discount_percent'], 2, '.', ''), '0'), '.').'%)'
                : '';
            $lines[] = 'Descuento'.$percentLabel.' −'.self::formatMoney($p['discount_total']);
            $lines[] = 'Subtotal con descuento '.self::formatMoney(round($p['subtotal'] - $p['discount_total'], 2));
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
