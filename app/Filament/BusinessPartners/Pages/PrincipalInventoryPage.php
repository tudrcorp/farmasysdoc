<?php

namespace App\Filament\BusinessPartners\Pages;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Models\Inventory;
use App\Models\User;
use App\Services\Orders\PrincipalInventoryOrderPlacer;
use App\Support\Inventory\LasDeliciasBranch;
use App\Support\Partners\PartnerPanel;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class PrincipalInventoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'inventario-las-delicias';

    protected static ?string $title = 'Inventario Las Delicias';

    protected static ?string $navigationLabel = 'Inventario Las Delicias';

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BuildingStorefront;

    protected string $view = 'filament.business-partners.pages.principal-inventory';

    public static function canAccess(): bool
    {
        return PartnerPanel::can(PartnerPanelPermission::PlacePrincipalInventoryOrders);
    }

    public function getSubheading(): ?string
    {
        return 'Inventario de Farmadoc Las Delicias. Marque uno o varios productos y pulse Hacer pedido.';
    }

    public function mount(): void
    {
        if (LasDeliciasBranch::resolve() !== null) {
            return;
        }

        Notification::make()
            ->title('Inventario no disponible')
            ->body('No hay una sucursal activa de Las Delicias.')
            ->warning()
            ->send();
    }

    public function table(Table $table): Table
    {
        $branchId = LasDeliciasBranch::resolve()?->getKey();

        return $table
            ->query($this->inventoryQuery($branchId))
            ->columns([
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $like = '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%';

                        return $query->whereHas('product', function (Builder $product) use ($like): void {
                            $product->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(barcode) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(brand, \'\')) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(presentation, \'\')) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(active_ingredient, \'\')) LIKE ?', [$like]);
                        });
                    })
                    ->wrap()
                    ->weight('medium'),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('product.barcode')
                    ->label('Código de barras')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('product.brand')
                    ->label('Marca')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('product.productCategory.name')
                    ->label('Categoría')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('product.active_ingredient')
                    ->label('Principio activo')
                    ->formatStateUsing(function (mixed $state): string {
                        $items = collect(is_array($state) ? $state : [$state])
                            ->filter(fn (mixed $value): bool => filled($value))
                            ->map(fn (mixed $value): string => (string) $value)
                            ->values();

                        return $items->isEmpty() ? '—' : $items->implode(', ');
                    })
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('product.concentration')
                    ->label('Concentración')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product.presentation')
                    ->label('Presentación')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('product.presentation_type')
                    ->label('Tipo de presentación')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product.unit_of_measure')
                    ->label('Unidad')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('product.requires_prescription')
                    ->label('Receta')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('product.is_controlled_substance')
                    ->label('Controlado')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('placeOrder')
                        ->label('Hacer pedido')
                        ->icon(Heroicon::ClipboardDocumentCheck)
                        ->modalHeading('Hacer pedido')
                        ->modalDescription('Indique la cantidad de cada producto. El pedido queda pendiente a nombre de su empresa, con sucursal Las Delicias.')
                        ->modalSubmitActionLabel('Crear pedido')
                        ->deselectRecordsAfterCompletion()
                        ->fillForm(fn (Collection $records): array => [
                            'lines' => $records
                                ->filter(fn (mixed $record): bool => $record instanceof Inventory)
                                ->map(fn (Inventory $record): array => [
                                    'inventory_id' => $record->getKey(),
                                    'product_name' => (string) ($record->product?->name ?? 'Producto'),
                                    'quantity' => 1,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->schema([
                            Repeater::make('lines')
                                ->label('Productos')
                                ->addable(false)
                                ->reorderable(false)
                                ->minItems(1)
                                ->schema([
                                    Hidden::make('inventory_id')->required(),
                                    TextInput::make('product_name')
                                        ->label('Producto')
                                        ->disabled()
                                        ->dehydrated(false),
                                    TextInput::make('quantity')
                                        ->label('Cantidad')
                                        ->numeric()
                                        ->integer()
                                        ->required()
                                        ->minValue(1)
                                        ->maxValue(9999)
                                        ->default(1),
                                ])
                                ->columns(2),
                        ])
                        ->action(function (array $data): void {
                            $actor = PartnerPanel::user();

                            if (! $actor instanceof User) {
                                Notification::make()->title('Su sesión no puede crear el pedido.')->danger()->send();

                                return;
                            }

                            try {
                                $order = app(PrincipalInventoryOrderPlacer::class)->place($actor, $data['lines'] ?? []);
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title((string) (collect($exception->errors())->flatten()->first() ?: 'No se pudo crear el pedido.'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Pedido creado')
                                ->body('Número '.$order->order_number.'.')
                                ->success()
                                ->send();

                            if (PartnerPanel::can(PartnerPanelPermission::ViewOrders)) {
                                $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading($branchId === null ? 'Inventario no disponible' : 'Sin productos')
            ->emptyStateDescription($branchId === null
                ? 'No hay una sucursal activa de Las Delicias.'
                : 'No hay productos activos en Las Delicias.')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    /**
     * @return Builder<Inventory>
     */
    private function inventoryQuery(int|string|null $branchId): Builder
    {
        $query = Inventory::query()
            ->select('inventories.*')
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->with(['product.productCategory']);

        if ($branchId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('inventories.branch_id', $branchId)
            ->where('products.is_active', true)
            ->orderBy('products.name');
    }
}
