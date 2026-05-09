<?php

namespace App\Filament\Resources\ProductTransferSales\Schemas;

use App\Enums\ProductTransferStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
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
use Filament\Support\Enums\Operation;
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

        $fromId = $get('../../from_branch_id');
        $selectedProductId = $get('product_id');
        $table = $query->getModel()->getTable();

        if (! filled($fromId) || (int) $fromId <= 0) {
            if (filled($selectedProductId)) {
                return $query
                    ->whereKey((int) $selectedProductId)
                    ->orderBy($query->qualifyColumn('name'));
            }

            return $query->whereRaw('0 = 1');
        }

        $fromId = (int) $fromId;

        return $query
            ->where(function (Builder $outer) use ($fromId, $table, $selectedProductId, $query): void {
                $outer->whereExists(function (QueryBuilder $sub) use ($fromId, $table): void {
                    $sub->from('inventories')
                        ->whereColumn('inventories.product_id', $table.'.id')
                        ->where('inventories.branch_id', $fromId)
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
        $relationshipTitleAttribute = $component->getRelationshipTitleAttribute();
        if (! str_contains((string) $relationshipTitleAttribute, '->')) {
            $relationshipTitleAttribute = $relationshipQuery->qualifyColumn((string) $relationshipTitleAttribute);
        }

        return $relationshipQuery
            ->pluck($relationshipTitleAttribute, $qualifiedRelatedKeyName)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function transferProductGlobalSearchResults(?string $search): array
    {
        $query = Product::query()
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->limit(30)
            ->select(['id', 'name', 'sku', 'barcode', 'active_ingredient']);

        $term = filled($search) ? trim((string) $search) : '';
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('active_ingredient', 'like', $like);
            });
        }

        return $query->get()
            ->mapWithKeys(fn (Product $product): array => [
                (int) $product->id => self::transferProductOptionLabelFromProduct($product),
            ])
            ->all();
    }

    public static function transferProductOptionLabel(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $product = Product::query()
            ->select(['id', 'name', 'sku', 'barcode', 'active_ingredient'])
            ->find((int) $value);

        if (! $product instanceof Product) {
            return null;
        }

        return self::transferProductOptionLabelFromProduct($product);
    }

    public static function transferProductOptionLabelFromProduct(Product $product): string
    {
        $code = filled($product->barcode)
            ? (string) $product->barcode
            : (filled($product->sku) ? (string) $product->sku : null);
        $activeIngredient = filled($product->active_ingredient)
            ? (is_array($product->active_ingredient)
                ? collect($product->active_ingredient)->filter()->implode(', ')
                : (string) $product->active_ingredient)
            : null;
        $segments = array_values(array_filter([
            $product->name,
            $code,
            $activeIngredient,
        ], fn (mixed $segment): bool => filled($segment)));

        return implode(' · ', $segments);
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

        $last = $rows[array_key_last($rows)] ?? null;
        if (! is_array($last) || filled($last['product_id'] ?? null)) {
            $rows[] = [
                'product_id' => null,
                'quantity' => 1,
            ];
        }

        $set('items', $rows);
    }

    public static function focusGlobalTransferProductSearchJs(): string
    {
        return <<<'JS'
setTimeout(() => {
    const selectors = [
        'input[placeholder="Código, nombre o principio activo (todas las sucursales)"]',
        'input[placeholder="Código, nombre o principio activo"]',
        'input[type="search"]',
    ];
    const input = selectors
        .map((selector) => document.querySelector(selector))
        .find((candidate) => candidate instanceof HTMLInputElement);
    if (input instanceof HTMLInputElement) {
        input.focus();
        input.select();
    }
}, 60);
JS;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->description('En edición verá el código generado al guardar el traslado.')
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
                                    ->rules(['different:to_branch_id'])
                                    ->validationMessages([
                                        'different' => 'La sucursal de origen debe ser distinta del destino.',
                                    ])
                                    ->helperText('Por defecto, inicia con la sucursal de su usuario.')
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
                    ->description('Busque por código, principio activo o nombre. Al elegir un producto se agrega automáticamente al detalle.')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        Select::make('sale_transfer_product_search')
                            ->label('Buscador general de productos')
                            ->placeholder('Código, nombre o principio activo (todas las sucursales)')
                            ->searchPrompt('Escriba código, principio activo o nombre')
                            ->helperText('Al seleccionar con click o Enter, se agrega en el repeater y el cursor vuelve a este buscador.')
                            ->live()
                            ->searchable()
                            ->searchDebounce(150)
                            ->getSearchResultsUsing(fn (?string $search): array => self::transferProductGlobalSearchResults($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::transferProductOptionLabel($value))
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
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText(fn (Get $get): string => filled($get('../../from_branch_id'))
                                        ? 'Solo productos con inventario disponible en la sucursal origen.'
                                        : 'Seleccione primero la sucursal origen.')
                                    ->prefixIcon(Heroicon::Cube),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->required()
                                    ->default(1)
                                    ->prefixIcon(Heroicon::Calculator),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Información de la factura del cliente')
                    ->description('Busque el cliente por nombre o documento, igual que en caja.')
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
                                    ->searchable()
                                    ->searchDebounce(100)
                                    ->getSearchResultsUsing(fn (?string $search): array => self::transferClientSearchResults($search))
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::transferClientOptionLabel($value))
                                    ->native(false)
                                    ->prefixIcon(Heroicon::User)
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
