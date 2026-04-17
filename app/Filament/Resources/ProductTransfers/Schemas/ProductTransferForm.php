<?php

namespace App\Filament\Resources\ProductTransfers\Schemas;

use App\Enums\ProductTransferStatus;
use App\Models\Product;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ProductTransferForm
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
        ];
    }

    /**
     * Sucursal solicitante (receptora): destino fijo a la sucursal de la sesión.
     */
    protected static function shouldLockToBranchToUser(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ! $user->isAdministrator()
            && ! $user->isDeliveryUser()
            && filled($user->branch_id);
    }

    /**
     * Fija `to_branch_id` al usuario de sucursal (no admin, no delivery).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enforceToBranchForRequestingBranch(array $data): array
    {
        if (self::shouldLockToBranchToUser()) {
            /** @var User $user */
            $user = auth()->user();
            $data['to_branch_id'] = (int) $user->branch_id;
        }

        return $data;
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
     * @return array<string | int, mixed>
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
                            ->helperText('Asignado automáticamente al crear (TRAS- + año + 000 + id).')
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->prefixIcon(Heroicon::QrCode)
                            ->autocomplete('off')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Movimiento de inventario')
                    ->description('La sucursal que **solicita** la mercancía es el **destino** (por defecto su sesión). Elija la sucursal **origen** que envía el stock.')
                    ->icon(Heroicon::ArrowsRightLeft)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('to_branch_id')
                                    ->label('Sucursal destino (solicitante)')
                                    ->relationship(
                                        name: 'toBranch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            $query->where('is_active', true)->orderBy('name');

                                            return BranchAuthScope::applyToBranchFormSelect($query);
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
                                    ->default(function (): ?int {
                                        $user = auth()->user();
                                        if (! $user instanceof User || $user->isAdministrator() || $user->isDeliveryUser()) {
                                            return null;
                                        }

                                        return filled($user->branch_id) ? (int) $user->branch_id : null;
                                    })
                                    ->disabled(fn (): bool => self::shouldLockToBranchToUser())
                                    ->dehydrated(true)
                                    ->helperText(fn (): string => self::shouldLockToBranchToUser()
                                        ? 'Su sucursal solicita el traslado (receptora); no puede cambiarse.'
                                        : 'Sucursal que recibirá la mercancía.')
                                    ->prefixIcon(Heroicon::MapPin),
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
                                    ->rules(['different:to_branch_id'])
                                    ->validationMessages([
                                        'different' => 'La sucursal de origen debe ser distinta del destino.',
                                    ])
                                    ->helperText('Sucursal que envía el stock.')
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Productos a trasladar')
                    ->description('Indique cada producto y la cantidad. Solo se listan productos con cantidad mayor que cero en inventario de la sucursal **origen**.')
                    ->icon(Heroicon::Cube)
                    ->schema([
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
                                        ? 'Búsqueda por nombre, SKU, código de barras o principio activo. Solo productos con cantidad > 0 en inventario de la sucursal origen.'
                                        : 'Seleccione primero la sucursal origen para listar productos con stock.')
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

                Section::make('Estado y tipo')
                    ->description('El alta queda en «Pendiente». Delivery pasa a «En proceso» al tomar el traslado. «Completado» aplica inventario y venta desde la acción en listado o vista (sucursal receptora). Solo administradores editan el estado aquí.')
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
                                        $user = auth()->user();
                                        if ($user instanceof User && $user->isAdministrator()) {
                                            $opts[ProductTransferStatus::Cancelled->value] = ProductTransferStatus::Cancelled->label();
                                        }

                                        return $opts;
                                    })
                                    ->required(function (?Model $record, Operation|string|null $operation): bool {
                                        $op = $operation instanceof Operation ? $operation->value : (string) $operation;

                                        return $op === Operation::Edit->value
                                            && auth()->user() instanceof User
                                            && auth()->user()->isAdministrator();
                                    })
                                    ->native(false)
                                    ->default(ProductTransferStatus::Pending->value)
                                    ->prefixIcon(Heroicon::Signal)
                                    ->visible(function (?Model $record, Operation|string|null $operation): bool {
                                        $op = $operation instanceof Operation ? $operation->value : (string) $operation;

                                        return $op === Operation::Edit->value
                                            && auth()->user() instanceof User
                                            && auth()->user()->isAdministrator();
                                    }),
                                Select::make('transfer_type')
                                    ->label('Tipo de traslado')
                                    ->options(self::transferTypeOptions())
                                    ->required()
                                    ->native(false)
                                    ->default('internal')
                                    ->prefixIcon(Heroicon::Squares2x2)
                                    ->visible(function (?Model $record, Operation|string|null $operation): bool {
                                        $op = $operation instanceof Operation ? $operation->value : (string) $operation;

                                        return $op !== Operation::Edit->value
                                            || (auth()->user() instanceof User && auth()->user()->isAdministrator());
                                    }),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
