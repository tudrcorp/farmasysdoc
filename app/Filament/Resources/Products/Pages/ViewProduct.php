<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\InventoryMovementType;
use App\Filament\Resources\Inventories\InventoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Detalle del Producto';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quickInventoryEntry')
                ->label('Dar entrada a inventario')
                ->icon(Heroicon::ArchiveBoxArrowDown)
                ->color('success')
                ->modalHeading('Entrada rápida de inventario')
                ->modalDescription('Registra la existencia inicial del producto en una sucursal usando solo los datos mínimos. El sistema copia tipo y principio(s) activo(s) automáticamente.')
                ->modalSubmitActionLabel('Guardar entrada')
                ->modalWidth('3xl')
                ->schema([
                    Grid::make([
                        'default' => 1,
                        'sm' => 2,
                    ])
                        ->schema([
                            Select::make('branch_id')
                                ->label('Sucursal destino')
                                ->options(fn (): array => Branch::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->required()
                                ->helperText('Solo se muestran sucursales activas.')
                                ->prefixIcon(Heroicon::BuildingStorefront),
                            TextInput::make('quantity')
                                ->label('Existencias iniciales')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->step(0.001)
                                ->default(0)
                                ->helperText('Cantidad física con la que inicia este producto en la sucursal.')
                                ->prefixIcon(Heroicon::SquaresPlus),
                            TextInput::make('reserved_quantity')
                                ->label('Cantidad reservada')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.001)
                                ->default(0)
                                ->helperText('Opcional. Usa 0 si no hay reservas.')
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

                    $inventory = Inventory::query()->updateOrCreate(
                        [
                            'branch_id' => $data['branch_id'],
                            'product_id' => $product->id,
                        ],
                        [
                            'quantity' => $data['quantity'],
                            'reserved_quantity' => $data['reserved_quantity'] ?? 0,
                            'allow_negative_stock' => (bool) ($data['allow_negative_stock'] ?? false),
                            'updated_by' => $actor,
                            'created_by' => $actor,
                        ],
                    );

                    if ($inventory->wasRecentlyCreated) {
                        InventoryMovement::query()->create([
                            'product_id' => $product->id,
                            'inventory_id' => $inventory->id,
                            'movement_type' => InventoryMovementType::Initial,
                            'quantity' => $data['quantity'],
                            'notes' => 'Entrada inicial registrada desde la ficha del producto.',
                            'created_by' => $actor,
                        ]);
                    }

                    Notification::make()
                        ->title('Entrada de inventario registrada')
                        ->body($inventory->wasRecentlyCreated
                            ? 'Inventario creado. Se registró automáticamente el movimiento inicial y se copiaron tipo/principio(s) activo(s) cuando aplica.'
                            : 'Inventario actualizado. Se copiaron tipo/principio(s) activo(s) cuando aplica.')
                        ->success()
                        ->send();

                    $this->redirect(InventoryResource::getUrl('view', ['record' => $inventory], isAbsolute: false));
                }),
            EditAction::make()
                ->label('Editar Producto')
                ->icon(Heroicon::Pencil)
                ->color('primary'),
        ];
    }
}
