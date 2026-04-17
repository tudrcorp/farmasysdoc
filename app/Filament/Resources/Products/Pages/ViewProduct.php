<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\InventoryMovementType;
use App\Filament\Resources\Products\Concerns\HasFarmaadminIosProductPage;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewProduct extends ViewRecord
{
    use HasFarmaadminIosProductPage;

    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Detalle del Producto';

    public static function authorizeResourceAccess(): void
    {
        if (ProductResource::canAccess()) {
            parent::authorizeResourceAccess();

            return;
        }

        $user = auth()->user();
        if (
            $user instanceof User
            && ! FarmaadminDeliveryUserAccess::denies(ProductResource::class)
            && (SaleResource::canAccess() || PurchaseResource::canAccess())
        ) {
            if ($parentResource = static::getParentResource()) {
                abort_unless($parentResource::canAccess(), 403);
            }

            return;
        }

        abort(403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quickInventoryEntry')
                ->label('Dar entrada a inventario')
                ->icon(Heroicon::ArchiveBoxArrowDown)
                ->color('success')
                ->modalHeading('Entrada rápida de inventario')
                ->modalDescription('Una fila por cada sucursal activa. Indica existencia inicial (si aplica), mínimo y máximo por ubicación. El sistema copia tipo y principio(s) activo(s) al guardar.')
                ->modalSubmitActionLabel('Guardar en todas las sucursales')
                ->modalWidth('5xl')
                ->fillForm(function (): array {
                    /** @var Product $product */
                    $product = $this->record;

                    $rows = Branch::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                        ->map(function (Branch $branch) use ($product): array {
                            $inv = Inventory::query()
                                ->where('product_id', $product->id)
                                ->where('branch_id', $branch->id)
                                ->first();

                            return [
                                'branch_id' => $branch->id,
                                'branch_name' => $branch->name,
                                'quantity' => $inv !== null ? (float) $inv->quantity : 0,
                                'minimum_stock' => $inv !== null && $inv->minimum_stock !== null ? (float) $inv->minimum_stock : null,
                                'maximum_stock' => $inv !== null && $inv->maximum_stock !== null ? (float) $inv->maximum_stock : null,
                                'reserved_quantity' => $inv !== null ? (float) $inv->reserved_quantity : 0,
                                'allow_negative_stock' => $inv !== null ? (bool) $inv->allow_negative_stock : false,
                            ];
                        })
                        ->values()
                        ->all();

                    return [
                        'branch_entries' => $rows,
                    ];
                })
                ->schema([
                    Repeater::make('branch_entries')
                        ->label('Sucursales')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->itemLabel(fn (array $state): ?string => $state['branch_name'] ?? null)
                        ->table([
                            TableColumn::make('Sucursal'),
                            TableColumn::make('Existencia'),
                            TableColumn::make('Mín.'),
                            TableColumn::make('Máx.'),
                            TableColumn::make('Reserv.'),
                            TableColumn::make('Negativo'),
                        ])
                        ->schema([
                            Hidden::make('branch_id'),
                            TextInput::make('branch_name')
                                ->label('Sucursal')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('quantity')
                                ->label('Existencia inicial / actual')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.001)
                                ->default(0)
                                ->required()
                                ->prefixIcon(Heroicon::SquaresPlus),
                            TextInput::make('minimum_stock')
                                ->label('Existencia mínima')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.001)
                                ->placeholder('—')
                                ->prefixIcon(Heroicon::ArrowTrendingDown),
                            TextInput::make('maximum_stock')
                                ->label('Existencia máxima')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.001)
                                ->placeholder('—')
                                ->prefixIcon(Heroicon::ArrowTrendingUp),
                            TextInput::make('reserved_quantity')
                                ->label('Reservada')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.001)
                                ->default(0)
                                ->prefixIcon(Heroicon::LockClosed),
                            Toggle::make('allow_negative_stock')
                                ->label('Permitir saldo negativo')
                                ->inline(false)
                                ->default(false),
                        ]),
                ])
                ->action(function (array $data): void {
                    /** @var Product $product */
                    $product = $this->record;

                    $actor = Auth::user()?->email
                        ?? Auth::user()?->name
                        ?? 'sistema';

                    /** @var list<array<string, mixed>> $entries */
                    $entries = $data['branch_entries'] ?? [];
                    $createdWithMovement = 0;
                    $processed = 0;

                    DB::transaction(function () use ($entries, $product, $actor, &$createdWithMovement, &$processed): void {
                        foreach ($entries as $row) {
                            $branchId = $row['branch_id'] ?? null;
                            if (blank($branchId)) {
                                continue;
                            }

                            $processed++;

                            $quantity = (float) ($row['quantity'] ?? 0);
                            $minimumRaw = $row['minimum_stock'] ?? null;
                            $maximumRaw = $row['maximum_stock'] ?? null;
                            $minimum = $minimumRaw === null || $minimumRaw === '' ? null : (float) $minimumRaw;
                            $maximum = $maximumRaw === null || $maximumRaw === '' ? null : (float) $maximumRaw;

                            $inventory = Inventory::query()->firstOrNew([
                                'branch_id' => (int) $branchId,
                                'product_id' => $product->id,
                            ]);

                            $wasExisting = $inventory->exists;

                            $inventory->fill([
                                'quantity' => $quantity,
                                'reserved_quantity' => (float) ($row['reserved_quantity'] ?? 0),
                                'minimum_stock' => $minimum,
                                'maximum_stock' => $maximum,
                                'allow_negative_stock' => (bool) ($row['allow_negative_stock'] ?? false),
                                'updated_by' => $actor,
                            ]);

                            if (! $wasExisting) {
                                $inventory->created_by = $actor;
                            }

                            $inventory->save();

                            if (! $wasExisting && $quantity > 0) {
                                InventoryMovement::query()->create([
                                    'product_id' => $product->id,
                                    'inventory_id' => $inventory->id,
                                    'movement_type' => InventoryMovementType::Initial,
                                    'quantity' => $quantity,
                                    'notes' => 'Entrada inicial registrada desde la ficha del producto (entrada masiva por sucursal).',
                                    'created_by' => $actor,
                                ]);
                                $createdWithMovement++;
                            }
                        }
                    });

                    Notification::make()
                        ->title('Inventario por sucursal guardado')
                        ->body(
                            'Sucursales guardadas: '.$processed
                                .'. Movimientos iniciales nuevos (existencia mayor que 0): '.$createdWithMovement.'.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(ProductResource::getUrl('view', ['record' => $product], isAbsolute: false));
                }),
            EditAction::make()
                ->label('Editar Producto')
                ->icon(Heroicon::Pencil)
                ->color('primary'),
        ];
    }
}
