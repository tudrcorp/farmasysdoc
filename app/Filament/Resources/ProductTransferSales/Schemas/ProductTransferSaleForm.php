<?php

namespace App\Filament\Resources\ProductTransferSales\Schemas;

use App\Enums\ProductTransferStatus;
use App\Filament\Resources\Sales\Actions\CashRegisterAction;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component as LivewireComponent;

class ProductTransferSaleForm
{
    /**
     * Puede elegir la sucursal emisora (origen): administrador, delivery, o GERENCIA con más de una sucursal en el pivote.
     * El resto opera en una sola sucursal y el origen queda fijado al valor por defecto de sesión.
     */
    public static function userMaySelectFromBranchOnSaleTransfer(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return true;
        }

        if ($user->hasGerenciaRole()) {
            return count($user->managedBranchIds()) > 1;
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function workflowStatusOptions(): array
    {
        return ProductTransferStatus::workflowOptions();
    }

    /**
     * @return array<string, string>
     */
    public static function transferTypeOptions(): array
    {
        return [
            'internal' => 'Interno',
            'external' => 'Externo',
            'adjustment' => 'Ajuste',
            'sale_transfer' => 'Traslado de venta',
        ];
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyTransferProductSelectBaseQuery(Builder $query, Get $get): Builder
    {
        $query->where($query->qualifyColumn('is_active'), true);

        $toId = $get('../../to_branch_id');
        $selectedProductId = $get('product_id');
        $table = $query->getModel()->getTable();

        if (! filled($toId) || (int) $toId <= 0) {
            if (filled($selectedProductId)) {
                return $query
                    ->whereKey((int) $selectedProductId)
                    ->orderBy($query->qualifyColumn('name'));
            }

            return $query->whereRaw('0 = 1');
        }

        $toId = (int) $toId;

        return $query
            ->where(function (Builder $outer) use ($toId, $table, $selectedProductId, $query): void {
                $outer->whereExists(function (QueryBuilder $sub) use ($toId, $table): void {
                    $sub->from('inventories')
                        ->whereColumn('inventories.product_id', $table.'.id')
                        ->where('inventories.branch_id', $toId)
                        ->where('inventories.quantity', '>', 0);
                });

                if (filled($selectedProductId)) {
                    $outer->orWhere($query->qualifyColumn('id'), (int) $selectedProductId);
                }
            })
            ->orderBy($query->qualifyColumn('name'));
    }

    /**
     * @return array<string|int, mixed>
     */
    public static function transferProductSelectSearchResults(Select $component, ?string $search): array
    {
        $relationship = Relation::noConstraints(fn () => $component->getRelationship());
        $relationshipQuery = app(RelationshipJoiner::class)->prepareQueryForNoConstraints($relationship);
        $relationshipQuery = self::applyTransferProductSelectBaseQuery($relationshipQuery, $component->makeGetUtility());

        $searchTrim = filled($search) ? trim((string) $search) : '';
        if ($searchTrim !== '') {
            $like = '%'.addcslashes($searchTrim, '%_\\').'%';
            $relationshipQuery->where(function (Builder $q) use ($like, $relationshipQuery): void {
                $q->where($relationshipQuery->qualifyColumn('name'), 'like', $like)
                    ->orWhere($relationshipQuery->qualifyColumn('sku'), 'like', $like)
                    ->orWhere($relationshipQuery->qualifyColumn('barcode'), 'like', $like)
                    ->orWhere($relationshipQuery->qualifyColumn('active_ingredient'), 'like', $like);
            });
        }

        $relationshipQuery->limit($component->getOptionsLimit());

        $qualifiedRelatedKeyName = $component->getQualifiedRelatedKeyNameForRelationship($relationship);
        $get = $component->makeGetUtility();
        $toId = (int) ($get('../../to_branch_id') ?? 0);
        if ($toId <= 0) {
            return [];
        }

        $ids = $relationshipQuery
            ->pluck($qualifiedRelatedKeyName)
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $options = [];
        foreach ($ids as $id) {
            $options[$id] = CashRegisterAction::saleTransferDestinationProductOptionLabel($toId, $id, $get)
                ?? (string) Product::query()->whereKey($id)->value('name');
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function transferProductGlobalSearchResults(?string $search, Get $get): array
    {
        $toId = (int) ($get('to_branch_id') ?? 0);
        if ($toId <= 0) {
            return [];
        }

        return CashRegisterAction::saleTransferDestinationProductSearch(
            $toId,
            filled($search) ? trim((string) $search) : '',
            $get,
        );
    }

    public static function transferProductGlobalOptionLabel(mixed $value, Get $get): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $toId = (int) ($get('to_branch_id') ?? 0);
        if ($toId <= 0) {
            return null;
        }

        return CashRegisterAction::saleTransferDestinationProductOptionLabel($toId, (int) $value, $get);
    }

    public static function transferRepeaterProductOptionLabel(mixed $value, Get $get): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $toId = (int) ($get('../../to_branch_id') ?? 0);
        if ($toId <= 0) {
            return Product::query()->whereKey((int) $value)->value('name');
        }

        return CashRegisterAction::saleTransferDestinationProductOptionLabel($toId, (int) $value, $get)
            ?? Product::query()->whereKey((int) $value)->value('name');
    }

    /**
     * @return array<int|string, string>
     */
    public static function transferClientSearchResults(?string $search): array
    {
        $term = trim((string) $search);
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
            ->where(function (Builder $query) use ($like): void {
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

    public static function transferClientOptionLabel(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $client = Client::query()
            ->select(['id', 'name', 'document_number'])
            ->find((int) $value);
        if (! $client instanceof Client) {
            return null;
        }

        return $client->name.(filled($client->document_number) ? ' · '.$client->document_number : '');
    }

    public static function appendProductFromGlobalSearchToItems(int $productId, Set $set, Get $get): void
    {
        $toId = (int) ($get('to_branch_id') ?? 0);
        if ($toId <= 0) {
            Notification::make()
                ->title('Seleccione sucursal destino')
                ->body('Elija la sucursal destino antes de agregar productos.')
                ->danger()
                ->send();

            return;
        }

        $hasStock = Inventory::query()
            ->where('branch_id', $toId)
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->exists();

        if (! $hasStock) {
            Notification::make()
                ->title('Sin existencia en destino')
                ->body('El producto no tiene inventario disponible en la sucursal destino.')
                ->danger()
                ->send();

            return;
        }

        $items = $get('items');
        $rows = [];

        if (is_array($items)) {
            $rows = array_values(array_map(function (mixed $row): array {
                if (! is_array($row)) {
                    return ['product_id' => null, 'quantity' => 1];
                }

                return [
                    'product_id' => $row['product_id'] ?? null,
                    'quantity' => filled($row['quantity'] ?? null) ? (float) $row['quantity'] : 1,
                ];
            }, $items));
        }

        $inserted = false;
        foreach ($rows as $index => $row) {
            if (! filled($row['product_id'] ?? null)) {
                $rows[$index]['product_id'] = $productId;
                $rows[$index]['quantity'] = filled($row['quantity'] ?? null) ? (float) $row['quantity'] : 1;
                $inserted = true;
                break;
            }
        }

        if (! $inserted) {
            $rows[] = [
                'product_id' => $productId,
                'quantity' => 1,
            ];
        }

        $set('items', $rows);
    }

    public static function focusGlobalTransferProductSearchJs(): string
    {
        return <<<'JS'
(() => {
    const wrapSel = '.farmadoc-sale-transfer-product-search-wrap';
    /** Solo un click: el toggle del botón cerraba el panel si se programaba dos veces (parpadeo). */
    let openClicked = false;
    let tries = 0;
    const maxTries = 35;

    const focusInPanel = (btn) => {
        const panelId = btn.getAttribute('aria-controls');
        if (!panelId) {
            return false;
        }
        const panel = document.getElementById(panelId);
        const input = panel?.querySelector(
            'input.fi-input:not([type="hidden"]), input[type="search"]',
        );
        if (input instanceof HTMLInputElement) {
            input.focus({ preventScroll: false });
            return true;
        }
        return false;
    };

    const step = () => {
        tries += 1;
        if (tries > maxTries) {
            return;
        }

        const wrap = document.querySelector(wrapSel);
        const btn = wrap?.querySelector('.fi-select-input-btn');
        if (!(btn instanceof HTMLElement)) {
            setTimeout(step, 80);
            return;
        }

        const expanded = btn.getAttribute('aria-expanded') === 'true';

        if (!expanded && !openClicked) {
            openClicked = true;
            btn.click();
            setTimeout(step, 100);
            return;
        }

        if (expanded && focusInPanel(btn)) {
            return;
        }

        setTimeout(step, 80);
    };

    setTimeout(step, 180);
})();
JS;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->description('En edición verá el código asignado al guardar (prefijo TV-; los demás traslados usan TRAS-).')
                    ->visibleOn('edit')
                    ->icon(Heroicon::Hashtag)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->visibleOn('edit')
                            ->disabled()
                            ->maxLength(100)
                            ->prefixIcon(Heroicon::QrCode)
                            ->autocomplete('off')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Movimiento de inventario')
                    ->description('Seleccione primero la sucursal origen y luego la sucursal destino del traslado de venta.')
                    ->icon(Heroicon::ArrowsRightLeft)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('from_branch_id')
                                    ->label('Sucursal origen (emisora)')
                                    ->relationship(
                                        name: 'fromBranch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                            $query->where('is_active', true)->orderBy('name');

                                            $toId = $get('to_branch_id');
                                            if (filled($toId)) {
                                                $query->whereKeyNot((int) $toId);
                                            }

                                            if (! self::userMaySelectFromBranchOnSaleTransfer()) {
                                                return BranchAuthScope::applyToBranchFormSelect($query);
                                            }

                                            return $query;
                                        },
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        if ((string) $get('to_branch_id') === (string) $state) {
                                            $set('to_branch_id', null);
                                        }
                                    })
                                    ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm())
                                    ->disabled(fn (): bool => ! self::userMaySelectFromBranchOnSaleTransfer())
                                    ->dehydrated(true)
                                    ->rules(['different:to_branch_id'])
                                    ->validationMessages([
                                        'different' => 'La sucursal de origen debe ser distinta del destino.',
                                    ])
                                    ->helperText(fn (): string => self::userMaySelectFromBranchOnSaleTransfer()
                                        ? 'Por defecto, inicia con la sucursal de su usuario.'
                                        : 'Sucursal de origen fija según su usuario; no puede modificarse.')
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                                Select::make('to_branch_id')
                                    ->label('Sucursal destino')
                                    ->relationship(
                                        name: 'toBranch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                            $query->where('is_active', true)->orderBy('name');

                                            $fromId = $get('from_branch_id');
                                            if (filled($fromId)) {
                                                $query->whereKeyNot((int) $fromId);
                                            }

                                            return $query;
                                        },
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        if ((string) $get('from_branch_id') === (string) $state) {
                                            $set('from_branch_id', null);
                                        }
                                    })
                                    ->helperText('Seleccione una sucursal distinta a la de origen.')
                                    ->prefixIcon(Heroicon::MapPin),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Productos a trasladar')
                    ->description('Busque por código, principio activo o nombre en la sucursal destino (solo artículos con existencia mayor que cero). Al elegir un producto se agrega al detalle.')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        Select::make('sale_transfer_product_search')
                            ->label('Buscador general de productos')
                            ->placeholder('Código, nombre o principio activo (sucursal destino)')
                            ->searchPrompt('Escriba código, principio activo o nombre')
                            ->helperText('Inventario de la sucursal destino con existencia > 0; precios como en caja. Al seleccionar, se agrega al detalle y el foco vuelve aquí.')
                            ->extraFieldWrapperAttributes([
                                'class' => 'farmadoc-sale-transfer-product-search-wrap',
                            ])
                            ->extraInputAttributes([
                                'data-sale-transfer-product-search' => '',
                            ])
                            ->live()
                            ->searchable()
                            ->searchDebounce(150)
                            ->getSearchResultsUsing(fn (?string $search, Get $get): array => self::transferProductGlobalSearchResults($search, $get))
                            ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => self::transferProductGlobalOptionLabel($value, $get))
                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get, Select $component): void {
                                if (! filled($state)) {
                                    return;
                                }

                                self::appendProductFromGlobalSearchToItems((int) $state, $set, $get);
                                $set('sale_transfer_product_search', null);

                                $livewire = $component->getLivewire();
                                if ($livewire instanceof LivewireComponent) {
                                    $livewire->js(self::focusGlobalTransferProductSearchJs());
                                }
                            })
                            ->native(false)
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->saveRelationshipsWhenHidden(false)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
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
                                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => self::applyTransferProductSelectBaseQuery($query, $get),
                                    )
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (Select $component, ?string $search): array => self::transferProductSelectSearchResults($component, $search))
                                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => self::transferRepeaterProductOptionLabel($value, $get))
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText(fn (Get $get): string => filled($get('../../to_branch_id'))
                                        ? 'Solo productos con existencia en la sucursal destino (etiqueta con precio y cantidad como en caja).'
                                        : 'Seleccione primero la sucursal destino.')
                                    ->prefixIcon(Heroicon::Cube),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->required()
                                    ->default(1)
                                    ->live(debounce: 150)
                                    ->inlinePrefix()
                                    ->inlineSuffix()
                                    ->prefixAction(
                                        Action::make('decreaseSaleTransferQuantity')
                                            ->label('Menos')
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
                                        Action::make('increaseSaleTransferQuantity')
                                            ->label('Más')
                                            ->icon(Heroicon::Plus)
                                            ->color('gray')
                                            ->size(Size::Small)
                                            ->action(function (Set $set, Get $get): void {
                                                $current = (float) ($get('quantity') ?? 0);
                                                $next = round(max(0.001, $current) + 1, 3);
                                                $toId = (int) ($get('../../to_branch_id') ?? 0);
                                                $productId = $get('product_id');
                                                if ($toId > 0 && filled($productId)) {
                                                    $available = (float) (Inventory::query()
                                                        ->where('branch_id', $toId)
                                                        ->where('product_id', (int) $productId)
                                                        ->value('quantity') ?? 0);
                                                    if ($next > $available + 0.0001) {
                                                        Notification::make()
                                                            ->title('Cantidad superior a la existencia en destino')
                                                            ->body('Ajuste la cantidad al inventario disponible en la sucursal destino.')
                                                            ->warning()
                                                            ->send();

                                                        return;
                                                    }
                                                }
                                                $set('quantity', $next);
                                            }),
                                        isInline: true,
                                    )
                                    ->extraAttributes([
                                        'class' => 'farmadoc-sale-transfer-qty-field',
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Información de la factura del cliente')
                    ->description('Busque el cliente por nombre o documento. Si no existe, regístrelo aquí mismo y continúe sin salir del traslado.')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('client_id')
                                    ->label('Cliente')
                                    ->placeholder('Nombre o documento de identidad…')
                                    ->live()
                                    ->searchable()
                                    ->searchDebounce(100)
                                    ->getSearchResultsUsing(fn (?string $search): array => self::transferClientSearchResults($search))
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::transferClientOptionLabel($value))
                                    ->native(false)
                                    ->prefixIcon(Heroicon::User)
                                    ->columnSpanFull(),
                                Section::make('Cliente nuevo')
                                    ->description('Visible cuando no selecciona un cliente existente. Complete nombre, cédula y teléfono para registrarlo automáticamente al guardar.')
                                    ->icon(Heroicon::UserPlus)
                                    ->iconColor('gray')
                                    ->visible(fn (Get $get): bool => blank($get('client_id')))
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
                                TextInput::make('customer_invoice_reference')
                                    ->label('Nro. de factura cliente')
                                    ->maxLength(120)
                                    ->prefixIcon(Heroicon::DocumentText)
                                    ->placeholder('Opcional'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Información del delivery')
                    ->description('Registre dirección e información de la persona que recibirá el pedido cuando aplique.')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Textarea::make('delivery_address')
                            ->label('Dirección de entrega')
                            ->rows(2)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('delivery_recipient_name')
                                    ->label('Persona que recibe')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Identification),
                                TextInput::make('delivery_recipient_phone')
                                    ->label('Teléfono de quien recibe')
                                    ->tel()
                                    ->maxLength(120)
                                    ->prefixIcon(Heroicon::Phone),
                            ]),
                        Textarea::make('delivery_notes')
                            ->label('Indicaciones de entrega')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Estado y tipo')
                    ->icon(Heroicon::Tag)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('status')
                                    ->label('Estado')
                                    ->options(function (): array {
                                        $opts = self::workflowStatusOptions();
                                        $user = Auth::user();
                                        if ($user instanceof User && $user->isAdministrator()) {
                                            $opts[ProductTransferStatus::Cancelled->value] = ProductTransferStatus::Cancelled->label();
                                        }

                                        return $opts;
                                    })
                                    ->required(function (Operation|string|null $operation): bool {
                                        $op = $operation instanceof Operation ? $operation->value : (string) $operation;

                                        return $op === Operation::Edit->value
                                            && Auth::user() instanceof User
                                            && Auth::user()->isAdministrator();
                                    })
                                    ->native(false)
                                    ->default(ProductTransferStatus::Pending->value)
                                    ->prefixIcon(Heroicon::Signal)
                                    ->visible(function (Operation|string|null $operation): bool {
                                        $op = $operation instanceof Operation ? $operation->value : (string) $operation;

                                        return $op === Operation::Edit->value
                                            && Auth::user() instanceof User
                                            && Auth::user()->isAdministrator();
                                    }),
                                Select::make('transfer_type')
                                    ->label('Tipo de traslado')
                                    ->options(self::transferTypeOptions())
                                    ->required()
                                    ->native(false)
                                    ->default('sale_transfer')
                                    ->prefixIcon(Heroicon::Squares2x2)
                                    ->visible(function (Operation|string|null $operation): bool {
                                        $op = $operation instanceof Operation ? $operation->value : (string) $operation;

                                        return $op !== Operation::Edit->value
                                            || (Auth::user() instanceof User && Auth::user()->isAdministrator());
                                    }),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
