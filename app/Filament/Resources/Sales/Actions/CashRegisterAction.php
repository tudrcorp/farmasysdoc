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
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;

final class CashRegisterAction
{
    /**
     * Tasa USD → VES solo como referencia visual (sustituir por API BCV u otra fuente).
     */
    private const PLACEHOLDER_USD_TO_VES_RATE = 55.28;

    public static function make(): Action
    {
        return Action::make('box')
            ->label('Caja')
            ->icon(Heroicon::Cube)
            ->color('primary')
            ->modalHeading('Caja registradora')
            ->modalDescription('Busque productos, indique cantidades y confirme el cobro. El total se actualiza al instante.')
            ->modalIcon(Heroicon::Banknotes)
            ->modalWidth(Width::SixExtraLarge)
            ->modalSubmitActionLabel('Registrar venta')
            ->modalCancelAction(fn (Action $action): Action => $action->color('danger'))
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
            ])
            ->fillForm(fn (): array => [
                'client_id' => null,
                'payment_method' => 'efectivo_usd',
                'mixed_usd_paid' => null,
                'reference' => null,
                'line_items' => [
                    [
                        'product_id' => null,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->schema([
                Section::make('Venta')
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->placeholder('Mostrador / sin cliente')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                $term = trim($search);
                                $like = '%'.addcslashes($term, '%_\\').'%';

                                return Client::query()
                                    ->where('status', 'active')
                                    ->where(function ($query) use ($like): void {
                                        $query->where('name', 'like', $like)
                                            ->orWhere('document_number', 'like', $like);
                                    })
                                    ->orderBy('name')
                                    ->limit(30)
                                    ->get()
                                    ->mapWithKeys(fn (Client $client): array => [
                                        $client->id => $client->name.(filled($client->document_number) ? ' · '.$client->document_number : ''),
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (blank($value)) {
                                    return null;
                                }

                                $client = Client::query()->find((int) $value);
                                if (! $client) {
                                    return null;
                                }

                                return $client->name.(filled($client->document_number) ? ' · '.$client->document_number : '');
                            })
                            ->native(false)
                            ->prefixIcon(Heroicon::User)
                            ->columnSpanFull(),
                        Repeater::make('line_items')
                            ->label('')
                            ->addActionLabel('Añadir producto')
                            ->reorderable()
                            ->defaultItems(1)
                            ->minItems(1)
                            ->live()
                            ->partiallyRenderAfterActionsCalled(false)
                            ->itemLabel(fn (array $state): ?string => filled($state['product_id'] ?? null)
                                ? (Product::query()->find($state['product_id'])?->name ?? 'Producto')
                                : 'Nueva línea')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'lg' => 12,
                                ])
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Producto')
                                            ->helperText('Se listan productos del inventario de su sucursal.')
                                            ->searchable()
                                            ->disabled(fn (): bool => blank(Auth::user()?->branch_id))
                                            ->getSearchResultsUsing(function (string $search): array {
                                                $branchId = Auth::user()?->branch_id;
                                                if (blank($branchId)) {
                                                    return [];
                                                }

                                                $term = trim($search);
                                                $query = Inventory::query()
                                                    ->where('branch_id', (int) $branchId)
                                                    ->whereHas('product', fn ($q) => $q->where('is_active', true))
                                                    ->with('product');

                                                if ($term !== '') {
                                                    $like = '%'.addcslashes($term, '%_\\').'%';
                                                    $query->where(function ($q) use ($like): void {
                                                        $q->whereHas('product', function ($productQuery) use ($like): void {
                                                            $productQuery->where('name', 'like', $like)
                                                                ->orWhere('barcode', 'like', $like)
                                                                ->orWhere('slug', 'like', $like);
                                                        });
                                                    });
                                                }

                                                return $query
                                                    ->whereNotNull('product_id')
                                                    ->orderBy('product_id')
                                                    ->limit($term === '' ? 25 : 40)
                                                    ->get()
                                                    ->mapWithKeys(function (Inventory $inventory): array {
                                                        $product = $inventory->product;
                                                        if (! $product) {
                                                            return [];
                                                        }

                                                        return [
                                                            $product->id => $product->name.
                                                                (filled($product->barcode) ? ' · '.$product->barcode : '').
                                                                ' — Costo '.self::formatMoney((float) ($product->cost_price ?? 0)),
                                                        ];
                                                    })
                                                    ->all();
                                            })
                                            ->getOptionLabelUsing(fn ($value): ?string => Product::query()->find($value)?->name)
                                            ->required()
                                            ->live()
                                            ->native(false)
                                            ->columnSpan(['lg' => 7]),
                                        TextInput::make('quantity')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->minValue(0.001)
                                            ->step(0.001)
                                            ->default(1)
                                            ->required()
                                            ->live(debounce: 300)
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
                                            ->columnSpan(['lg' => 2]),
                                        TextEntry::make('line_preview')
                                            ->label('Importe línea')
                                            ->state(fn (Get $get): string => self::formatMoney(self::computeLineTotal($get)))
                                            ->dehydrated(false)
                                            ->columnSpan(['lg' => 3]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'farmadoc-pos-meta-section farmadoc-pos-cart-section',
                    ]),

                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])
                    ->schema([
                        Section::make('Total a cobrar')
                            ->description('Incluye impuestos según cada producto.')
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
                                            ->size(TextSize::Large)
                                            ->state(fn (Get $get): string => self::formatMoney(self::computeSaleTotal($get)))
                                            ->dehydrated(false)
                                            ->extraEntryWrapperAttributes([
                                                'class' => 'farmadoc-pos-total-ios__amount',
                                            ]),
                                        TextEntry::make('pos_total_banner_ves')
                                            ->hiddenLabel()
                                            ->alignment(Alignment::Center)
                                            ->html()
                                            ->state(function (Get $get): HtmlString {
                                                $ves = self::formatBolivaresReference(self::computeSaleTotal($get));

                                                return new HtmlString(
                                                    '<p class="farmadoc-pos-total-ios__ves">≈ '.e($ves).'</p>'.
                                                    '<p class="farmadoc-pos-total-ios__hint">Tasa referencia · API próximamente</p>'
                                                );
                                            })
                                            ->dehydrated(false)
                                            ->extraEntryWrapperAttributes([
                                                'class' => 'farmadoc-pos-total-ios__sub',
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->extraAttributes([
                                'class' => 'farmadoc-pos-total-section farmadoc-pos-total-section--ios',
                            ]),
                        Section::make('Formas de pago')
                            ->icon(Heroicon::CreditCard)
                            ->schema([
                                Select::make('payment_method')
                                    ->label('Cobro')
                                    ->options([
                                        'transfer_usd' => 'Transferencias USD',
                                        'transfer_ves' => 'Transferencia VES',
                                        'pago_movil' => 'Pago Mobil',
                                        'zelle' => 'Zelle',
                                        'efectivo_usd' => 'Efectivo USD',
                                        'mixed' => 'Pago Multiple',
                                    ])
                                    ->default('efectivo_usd')
                                    ->required()
                                    ->live()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::CreditCard),
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
                                    ->visible(fn (Get $get): bool => in_array($get('payment_method'), ['transfer_ves', 'pago_movil', 'zelle', 'mixed'], true)),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
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

                $lines = collect($data['line_items'] ?? [])
                    ->filter(fn (array $row): bool => filled($row['product_id'] ?? null)
                        && (float) ($row['quantity'] ?? 0) > 0)
                    ->values()
                    ->all();

                if ($lines === []) {
                    Notification::make()
                        ->title('Agregue al menos un producto con cantidad válida.')
                        ->danger()
                        ->send();

                    return;
                }

                $productIds = collect($lines)->pluck('product_id')->unique()->values()->all();
                $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

                $branchId = (int) $branchId;
                $subtotal = 0.0;
                $taxTotal = 0.0;

                $payloadItems = [];

                foreach ($lines as $row) {
                    $productId = (int) $row['product_id'];
                    $qty = (float) $row['quantity'];
                    $product = $products->get($productId);
                    if (! $product) {
                        continue;
                    }

                    if (! Inventory::query()
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->exists()) {
                        Notification::make()
                            ->title('Producto no disponible en la sucursal seleccionada')
                            ->body('Revise el carrito: '.$product->name.' no tiene inventario en esta sucursal.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $unit = (float) $product->sale_price;
                    $unitCost = (float) ($product->cost_price ?? 0);
                    $taxRate = (float) ($product->tax_rate ?? 0);
                    $lineSubtotal = round($qty * $unit, 2);
                    $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);
                    $lineTotal = round($lineSubtotal + $taxAmount, 2);
                    $lineCostTotal = round($qty * $unitCost, 2);
                    $grossProfit = round($lineTotal - $lineCostTotal, 2);

                    $subtotal += $lineSubtotal;
                    $taxTotal += $taxAmount;

                    $inventoryId = Inventory::query()
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->value('id');

                    $payloadItems[] = [
                        'product_id' => $productId,
                        'inventory_id' => $inventoryId ? (int) $inventoryId : null,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'unit_cost' => $unitCost,
                        'discount_amount' => 0,
                        'tax_rate' => $taxRate,
                        'line_subtotal' => $lineSubtotal,
                        'tax_amount' => $taxAmount,
                        'line_total' => $lineTotal,
                        'line_cost_total' => $lineCostTotal,
                        'gross_profit' => $grossProfit,
                        'product_name_snapshot' => $product->name,
                        'sku_snapshot' => $product->barcode,
                    ];
                }

                if ($payloadItems === []) {
                    Notification::make()
                        ->title('No se pudieron resolver los productos seleccionados.')
                        ->danger()
                        ->send();

                    return;
                }

                $discountTotal = 0.0;
                $documentTotal = round($subtotal + $taxTotal - $discountTotal, 2);

                [$paymentUsd, $paymentVes] = self::resolvePaymentAmounts(
                    $documentTotal,
                    $paymentMethod,
                    mixedUsdPaid: (float) ($data['mixed_usd_paid'] ?? 0)
                );

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

                $actor = Auth::user()?->email
                    ?? Auth::user()?->name
                    ?? 'sistema';

                try {
                    $sale = DB::transaction(function () use ($branchId, $data, $payloadItems, $lines, $products, $subtotal, $taxTotal, $discountTotal, $documentTotal, $actor, $paymentMethod, $paymentUsd, $paymentVes, $paymentReference): Sale {
                        $qtyByProduct = [];
                        foreach ($lines as $row) {
                            $pid = (int) $row['product_id'];
                            $qtyByProduct[$pid] = ($qtyByProduct[$pid] ?? 0.0) + (float) $row['quantity'];
                        }
                        ksort($qtyByProduct);

                        foreach ($qtyByProduct as $productId => $totalQty) {
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
                            'discount_total' => round($discountTotal, 2),
                            'total' => $documentTotal,
                            'payment_method' => $paymentMethod,
                            'payment_usd' => round($paymentUsd, 2),
                            'payment_ves' => round($paymentVes, 2),
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
                                'unit_cost' => (float) ($product->cost_price ?? 0),
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

                return redirect()->to(SaleResource::getUrl('view', ['record' => $sale], isAbsolute: false));
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

    private static function formatBolivaresReference(float $usdAmount): string
    {
        $ves = round($usdAmount * self::PLACEHOLDER_USD_TO_VES_RATE, 2);

        return 'Bs. '.number_format($ves, 2, ',', '.');
    }

    private static function formatBolivaresReferenceFromVes(float $vesAmount): string
    {
        return 'Bs. '.number_format(round($vesAmount, 2), 2, ',', '.');
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function resolvePaymentAmounts(float $documentTotalUsd, string $paymentMethod, float $mixedUsdPaid = 0): array
    {
        $rate = self::PLACEHOLDER_USD_TO_VES_RATE;

        return match ($paymentMethod) {
            'efectivo_usd', 'transfer_usd', 'zelle' => [$documentTotalUsd, 0.0],
            'transfer_ves', 'pago_movil' => [0.0, round($documentTotalUsd * $rate, 2)],
            'mixed' => [
                round(max(0.0, min($documentTotalUsd, $mixedUsdPaid)), 2),
                round(max(0.0, $documentTotalUsd - max(0.0, min($documentTotalUsd, $mixedUsdPaid))) * $rate, 2),
            ],
            default => [$documentTotalUsd, 0.0],
        };
    }

    /**
     * @return array{payment_usd: float, payment_ves: float}
     */
    private static function computePaymentBreakdownForForm(Get $get): array
    {
        $paymentMethod = (string) ($get('payment_method') ?? 'efectivo_usd');
        $total = self::computeSaleTotal($get);
        $mixedUsdPaid = (float) ($get('mixed_usd_paid') ?? 0);
        [$paymentUsd, $paymentVes] = self::resolvePaymentAmounts($total, $paymentMethod, $mixedUsdPaid);

        return [
            'payment_usd' => $paymentUsd,
            'payment_ves' => $paymentVes,
        ];
    }

    private static function computeSaleTotal(Get $get): float
    {
        $rows = $get('line_items') ?? [];
        if (! is_array($rows)) {
            return 0.0;
        }

        $productIds = collect($rows)->pluck('product_id')->filter()->unique()->values()->all();
        if ($productIds === []) {
            return 0.0;
        }

        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $sum = 0.0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pid = $row['product_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);
            if (! filled($pid) || $qty <= 0) {
                continue;
            }
            $product = $products->get((int) $pid);
            if (! $product) {
                continue;
            }
            $unit = (float) $product->sale_price;
            $taxRate = (float) ($product->tax_rate ?? 0);
            $lineSubtotal = $qty * $unit;
            $taxAmount = $lineSubtotal * ($taxRate / 100);
            $sum += $lineSubtotal + $taxAmount;
        }

        return round($sum, 2);
    }

    private static function computeLineTotal(Get $get): float
    {
        $productId = $get('product_id');
        $qty = (float) ($get('quantity') ?? 0);
        if (! filled($productId) || $qty <= 0) {
            return 0.0;
        }

        $product = Product::query()->find((int) $productId);
        if (! $product) {
            return 0.0;
        }

        $unit = (float) $product->sale_price;
        $taxRate = (float) ($product->tax_rate ?? 0);
        $lineSubtotal = $qty * $unit;
        $taxAmount = $lineSubtotal * ($taxRate / 100);

        return round($lineSubtotal + $taxAmount, 2);
    }
}
