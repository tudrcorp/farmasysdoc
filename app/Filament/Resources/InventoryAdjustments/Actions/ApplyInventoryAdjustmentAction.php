<?php

namespace App\Filament\Resources\InventoryAdjustments\Actions;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Inventory\InventoryAdjustmentApplyService;
use App\Support\Filament\BranchAuthScope;
use App\Support\Inventory\InventoryAdjustmentReason;
use App\Support\Inventory\InventoryQuantityFormat;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApplyInventoryAdjustmentAction
{
    public const NAME = 'applyInventoryAdjustment';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Nuevo ajuste')
            ->icon(Heroicon::AdjustmentsHorizontal)
            ->color('primary')
            ->modalHeading('Aplicar ajuste de inventario')
            ->modalDescription('Ajusta existencias por sucursal y producto. Se registran inventario, movimiento y trazas de seguridad con detalles.')
            ->modalSubmitActionLabel('Aplicar ajuste')
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes([
                'data-farmadoc-inventory-adjustment-modal' => '1',
            ])
            ->form(self::formSchema())
            ->action(self::submitAction(...));
    }

    /**
     * @return array<int, Component>
     */
    public static function formSchema(): array
    {
        return [
            Select::make('branch_id')
                ->label('Sucursal')
                ->options(fn (): array => BranchAuthScope::applyToBranchFormSelect(
                    Branch::query()->where('is_active', true)->orderBy('name'),
                )->pluck('name', 'id')->toArray())
                ->default(fn (): ?int => self::defaultBranchIdForAdjustment())
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->live()
                ->disabled(fn (): bool => self::shouldLockBranchSelectionForAdjustment())
                ->dehydrated(true)
                ->helperText(fn (): string => self::branchSelectHelperText())
                ->prefixIcon(Heroicon::BuildingStorefront)
                ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                    if (blank($state)) {
                        return;
                    }

                    $items = $get('items');
                    if (! is_array($items) || $items === []) {
                        return;
                    }

                    $set('items', array_map(
                        static fn (mixed $row): array => array_merge(
                            is_array($row) ? $row : [],
                            ['product_id' => null],
                        ),
                        $items,
                    ));
                }),

            Select::make('reason')
                ->label('Tipo de ajuste')
                ->options(InventoryAdjustmentReason::options())
                ->required()
                ->native(false)
                ->searchable(),

            Textarea::make('notes')
                ->label('Notas (opcional)')
                ->rows(3)
                ->columnSpanFull(),

            Repeater::make('items')
                ->label('')
                ->reorderable(false)
                ->addActionLabel('Añadir producto')
                ->defaultItems(1)
                ->minItems(1)
                ->table([
                    TableColumn::make('Producto (nombre, código, PA)')->width('42%'),
                    TableColumn::make('Cantidad')->width('7rem'),
                    TableColumn::make('Costo (USD)')->width('9rem'),
                    TableColumn::make('Categoría')->width('16rem'),
                ])
                ->schema([
                    Select::make('product_id')
                        ->label('Producto')
                        ->placeholder('Código, nombre o PA')
                        ->required()
                        ->live()
                        ->searchable()
                        ->searchPrompt('Escriba nombre, principio activo o código')
                        ->searchDebounce(150)
                        ->searchingMessage('Buscando productos…')
                        ->noSearchResultsMessage('Sin coincidencias. Pulse Intro (Enter) para registrar el producto con el texto que escribió.')
                        ->helperText('Si no aparece en la lista, pulse Intro en el buscador para crear el producto y usarlo en esta línea.')
                        ->extraAlpineAttributes(self::productSelectQuickCreateDataAttributes())
                        ->disabled(fn (Get $get): bool => (int) ($get('../../branch_id') ?? 0) <= 0)
                        ->getSearchResultsUsing(function (string $search, Get $get): array {
                            $branchId = (int) ($get('../../branch_id') ?? 0);
                            if ($branchId <= 0) {
                                return [];
                            }

                            return self::productSearchResultsForInventoryAdjustment($branchId, $search);
                        })
                        ->getOptionLabelUsing(function ($value, Get $get): ?string {
                            $branchId = (int) ($get('../../branch_id') ?? 0);
                            if ($branchId <= 0 || blank($value)) {
                                return null;
                            }

                            return self::productOptionLabelForInventoryAdjustment($branchId, (int) $value);
                        })
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $productId = (int) ($state ?? 0);
                            if ($productId <= 0) {
                                return;
                            }

                            $product = Product::query()
                                ->select(['id', 'cost_price', 'product_category_id'])
                                ->whereKey($productId)
                                ->first();

                            if (! $product instanceof Product) {
                                return;
                            }

                            $cost = round(max(0.0, (float) ($product->cost_price ?? 0)), 2);
                            $needsManualCost = $cost <= 0.00001;

                            $set('manual_cost_required', $needsManualCost);
                            $set('unit_cost_snapshot', $needsManualCost ? null : $cost);
                            $set('product_category_id', $product->product_category_id !== null ? (int) $product->product_category_id : null);
                        })
                        ->native(false)
                        ->prefixIcon(Heroicon::Cube),

                    TextInput::make('quantity')
                        ->label('Cantidad')
                        ->numeric()
                        ->minValue(0.001)
                        ->step(0.001)
                        ->required()
                        ->prefixIcon(Heroicon::Hashtag),

                    Hidden::make('manual_cost_required')
                        ->default(false)
                        ->dehydrated(),

                    TextInput::make('unit_cost_snapshot')
                        ->label('Costo unitario (USD)')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->dehydrated()
                        ->required(fn (Get $get): bool => (bool) $get('manual_cost_required', false))
                        ->hint(fn (Get $get): ?string => (bool) $get('manual_cost_required', false)
                            ? 'Producto sin costo. Indique costo unitario.'
                            : null),

                    Select::make('product_category_id')
                        ->label('Categoría (para cálculo)')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => (bool) $get('manual_cost_required', false))
                        ->options(fn (): array => ProductCategory::query()
                            ->where('is_active', true)
                            ->orderBy('name', 'asc')
                            ->pluck('name', 'id')
                            ->all())
                        ->visible(fn (Get $get): bool => (bool) $get('manual_cost_required', false))
                        ->prefixIcon(Heroicon::Tag),
                ]),
        ];
    }

    public static function submitAction(array $data): void
    {
        $branchId = (int) ($data['branch_id'] ?? 0);
        $reason = (string) ($data['reason'] ?? '');
        $notes = isset($data['notes']) && is_string($data['notes']) ? trim($data['notes']) : null;
        $items = is_array($data['items'] ?? null) ? ($data['items'] ?? []) : [];

        self::assertBranchIdPermittedForAuthenticatedUser($branchId);

        try {
            app(InventoryAdjustmentApplyService::class)->apply(
                branchId: $branchId,
                reason: $reason,
                items: $items,
                notes: $notes,
                actor: Auth::user(),
            );

            Notification::make()
                ->title('Ajuste aplicado')
                ->body('Inventario actualizado y trazas registradas.')
                ->success()
                ->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo aplicar el ajuste')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->danger()
                ->send();
        }
    }

    public static function assertBranchIdPermittedForAuthenticatedUser(int $branchId): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'branch_id' => 'Debe iniciar sesión para aplicar ajustes.',
            ]);
        }

        if ($user->isAdministrator()) {
            $exists = Branch::query()
                ->whereKey($branchId)
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Sucursal inválida o inactiva.',
                ]);
            }

            return;
        }

        $permittedIds = $user->restrictedBranchIdsForQueries();
        if ($permittedIds === [] || ! in_array($branchId, $permittedIds, true)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Seleccione una sucursal asignada a su usuario.',
            ]);
        }
    }

    public static function shouldLockBranchSelectionForAdjustment(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->isAdministrator()) {
            return false;
        }

        return count($user->restrictedBranchIdsForQueries()) === 1;
    }

    public static function defaultBranchIdForAdjustment(): ?int
    {
        $suggested = BranchAuthScope::suggestedBranchIdForOperationalForm();
        if ($suggested !== null) {
            return $suggested;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        $permittedIds = $user->restrictedBranchIdsForQueries();

        return count($permittedIds) === 1 ? $permittedIds[0] : null;
    }

    public static function branchSelectHelperText(): string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return 'Sucursal donde se aplicará el ajuste.';
        }

        if (self::shouldLockBranchSelectionForAdjustment()) {
            return 'Su sucursal asignada; el ajuste se aplicará en esta sucursal.';
        }

        if ($user->hasGerenciaRole() && count($user->managedBranchIds()) > 1) {
            return 'Elija la sucursal donde aplicará el ajuste.';
        }

        if ($user->isManager() && ! $user->hasGerenciaRole()) {
            return 'Sucursal donde se aplicará el ajuste.';
        }

        return 'Sucursal donde se aplicará el ajuste.';
    }

    /**
     * Marca el buscador de producto del repeater (el listener Enter se registra en la página).
     *
     * @return array<string, string>
     */
    public static function productSelectQuickCreateDataAttributes(): array
    {
        return [
            'data-inventory-adjustment-product-quick-create' => '1',
        ];
    }

    /**
     * Listener document-level: el select del modal de acción no propaga bien $wire con Alpine en el propio campo.
     * Se registra una vez al montar {@see ListInventoryAdjustments}.
     */
    public static function registerQuickCreateEnterListenerJs(): string
    {
        return <<<'JS'
            (() => {
                if (window.__farmadocInvAdjProductQuickCreateEnter) {
                    return;
                }
                window.__farmadocInvAdjProductQuickCreateEnter = true;
                document.addEventListener('keydown', (ev) => {
                    if (ev.key !== 'Enter') {
                        return;
                    }
                    const el = ev.target;
                    if (!(el instanceof HTMLInputElement)) {
                        return;
                    }
                    if (!el.closest('.fi-select-input-search-ctn')) {
                        return;
                    }
                    const panel = el.closest('.fi-dropdown-panel');
                    if (!panel) {
                        return;
                    }
                    const host = panel.closest('[data-inventory-adjustment-product-quick-create]');
                    if (!host) {
                        return;
                    }
                    const modal = el.closest('.fi-modal-window');
                    if (!modal || !modal.hasAttribute('data-farmadoc-inventory-adjustment-modal')) {
                        return;
                    }
                    const search = (el.value || '').trim();
                    if (!search) {
                        return;
                    }
                    if (panel.querySelectorAll('li[role=option]').length > 0) {
                        return;
                    }
                    const msg = panel.querySelector('.fi-select-input-message');
                    if (msg && /buscando|searching/i.test(msg.textContent || '')) {
                        return;
                    }
                    let repeaterKey = '';
                    const row = host.closest('tr[x-sortable-item]') || host.closest('.fi-fo-repeater-item');
                    if (row) {
                        repeaterKey = row.getAttribute('x-sortable-item') || '';
                        if (!repeaterKey) {
                            const wireKey = row.getAttribute('wire:key') || '';
                            const match = wireKey.match(/items\.([^.]+)/);
                            if (match) {
                                repeaterKey = match[1];
                            }
                        }
                    }
                    ev.preventDefault();
                    ev.stopImmediatePropagation();
                    el.blur();
                    $wire.openQuickCreateProductModalFromAdjustmentSelectSearch(search, repeaterKey);
                }, true);
            })();
            JS;
    }

    /**
     * @return array<int, string>
     */
    private static function productSearchResultsForInventoryAdjustment(int $branchId, string $search): array
    {
        $term = trim($search);

        $query = DB::table('products')
            ->leftJoin('inventories as inv', function ($join) use ($branchId): void {
                $join->on('inv.product_id', '=', 'products.id')
                    ->where('inv.branch_id', '=', $branchId);
            })
            ->select(['products.id', 'products.name', 'products.barcode', 'products.sku', 'products.active_ingredient', 'inv.quantity as branch_qty'])
            ->where('products.is_active', true)
            ->orderBy('products.name')
            ->limit($term === '' ? 25 : 40);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $ingredientLike = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';

            $query->where(function ($w) use ($like, $ingredientLike): void {
                $w->where('products.name', 'like', $like)
                    ->orWhere('products.barcode', 'like', $like)
                    ->orWhere('products.sku', 'like', $like)
                    ->orWhereRaw('LOWER(products.active_ingredient) LIKE ?', [$ingredientLike]);
            });
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        return $rows->mapWithKeys(function ($row): array {
            $label = filled($row->barcode)
                ? $row->barcode.' · '.$row->name
                : $row->name;

            $pa = self::firstActiveIngredientText($row->active_ingredient);
            if (filled($pa)) {
                $label .= ' · PA '.$pa;
            }

            $qty = (float) ($row->branch_qty ?? 0);
            $label .= ' · Cant. '.InventoryQuantityFormat::display(max(0.0, $qty));

            return [(int) $row->id => (string) $label];
        })->all();
    }

    private static function productOptionLabelForInventoryAdjustment(int $branchId, int $productId): ?string
    {
        $row = DB::table('products')
            ->leftJoin('inventories as inv', function ($join) use ($branchId): void {
                $join->on('inv.product_id', '=', 'products.id')
                    ->where('inv.branch_id', '=', $branchId);
            })
            ->select(['products.id', 'products.name', 'products.barcode', 'products.sku', 'products.active_ingredient', 'inv.quantity as branch_qty'])
            ->where('products.id', $productId)
            ->where('products.is_active', true)
            ->first();

        if ($row === null) {
            return null;
        }

        $base = filled($row->barcode)
            ? $row->barcode.' · '.$row->name
            : $row->name;

        $pa = self::firstActiveIngredientText($row->active_ingredient);
        if (filled($pa)) {
            $base .= ' · PA '.$pa;
        }

        $qty = (float) ($row->branch_qty ?? 0);
        $base .= ' · Cant. '.InventoryQuantityFormat::display(max(0.0, $qty));

        return (string) $base;
    }

    private static function firstActiveIngredientText(mixed $activeIngredient): ?string
    {
        if ($activeIngredient === null) {
            return null;
        }

        if (is_array($activeIngredient)) {
            $first = array_values(array_filter($activeIngredient, fn (mixed $v): bool => is_string($v) && filled($v)))[0] ?? null;

            return is_string($first) ? $first : null;
        }

        if (is_string($activeIngredient)) {
            $decoded = json_decode($activeIngredient, true);
            if (is_array($decoded)) {
                $first = array_values(array_filter($decoded, fn (mixed $v): bool => is_string($v) && filled($v)))[0] ?? null;

                return is_string($first) ? $first : null;
            }

            $t = trim($activeIngredient);

            return $t !== '' ? $t : null;
        }

        return null;
    }
}
