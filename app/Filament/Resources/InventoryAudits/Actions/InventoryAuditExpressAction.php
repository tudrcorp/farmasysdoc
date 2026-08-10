<?php

namespace App\Filament\Resources\InventoryAudits\Actions;

use App\Filament\Resources\InventoryAudits\Support\InventoryAuditApplyUpdateForm;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryAuditLine;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryAuditApplyService;
use App\Support\Filament\BranchAuthScope;
use App\Support\Inventory\InventoryQuantityFormat;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

final class InventoryAuditExpressAction
{
    public const NAME = 'expressAudit';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Auditoría Express')
            ->icon(Heroicon::Bolt)
            ->color('warning')
            ->modalHeading('Auditoría Express')
            ->modalDescription('Audite un producto de forma individual: busque por nombre, código o principio activo y aplique la misma actualización que en el ciclo masivo.')
            ->modalSubmitActionLabel('Aplicar')
            ->modalWidth(Width::Large)
            ->form(self::formSchema())
            ->tap(fn (Action $action): Action => InventoryAuditApplyUpdateForm::configureOtpGatedModalSubmit($action, 'Guardar cambios'))
            ->successNotificationTitle('Cambio aplicado con éxito')
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Cambio aplicado con éxito')
                    ->body('El producto fue actualizado correctamente.')
            )
            ->action(self::submitAction(...));
    }

    /**
     * @return array<int, mixed>
     */
    public static function formSchema(): array
    {
        return [
            Select::make('branch_id')
                ->label('Sucursal')
                ->options(fn (): array => BranchAuthScope::applyToBranchFormSelect(
                    Branch::query()->where('is_active', true)->orderBy('name'),
                )->pluck('name', 'id')->all())
                ->default(function (): ?int {
                    $user = Auth::user();
                    if (! $user instanceof User) {
                        return null;
                    }

                    $ids = $user->isAdministrator()
                        ? []
                        : $user->restrictedBranchIdsForQueries();

                    if (count($ids) === 1) {
                        return $ids[0];
                    }

                    return filled($user->branch_id) ? (int) $user->branch_id : null;
                })
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    self::clearProductSelection($set);
                })
                ->prefixIcon(Heroicon::BuildingStorefront),

            Select::make('product_id')
                ->label('Producto')
                ->placeholder('Nombre, código o principio activo')
                ->required()
                ->searchable()
                ->searchPrompt('Escriba nombre, código o principio activo')
                ->searchDebounce(150)
                ->searchingMessage('Buscando productos…')
                ->noSearchResultsMessage('Sin coincidencias en el inventario de esta sucursal.')
                ->live()
                ->disabled(fn (Get $get): bool => (int) ($get('branch_id') ?? 0) <= 0)
                ->getSearchResultsUsing(function (string $search, Get $get): array {
                    $branchId = (int) ($get('branch_id') ?? 0);
                    if ($branchId <= 0) {
                        return [];
                    }

                    return self::productSearchResults($branchId, $search);
                })
                ->getOptionLabelUsing(function ($value, Get $get): ?string {
                    $branchId = (int) ($get('branch_id') ?? 0);
                    if ($branchId <= 0 || blank($value)) {
                        return null;
                    }

                    return self::productOptionLabel($branchId, (int) $value);
                })
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                    self::hydrateProductContext((int) ($state ?? 0), $get, $set);
                })
                ->prefixIcon(Heroicon::Cube),

            Hidden::make('_blocked_by_open_audit')->default(false)->dehydrated(),
            Hidden::make('_can_remove_from_open_audit')->default(false)->dehydrated(false),
            Hidden::make('_open_audit_id')->default(null)->dehydrated(false),
            Hidden::make('_open_audit_line_id')->default(null)->dehydrated(false),
            Hidden::make('_block_message')->default(null)->dehydrated(false),

            Placeholder::make('open_audit_block_notice')
                ->hiddenLabel()
                ->content(fn (Get $get): HtmlString => new HtmlString(
                    '<div class="rounded-xl border border-warning-500/40 bg-warning-500/10 p-3 text-sm text-warning-700 dark:text-warning-200">'
                    .'<p class="font-medium">Producto en auditoría abierta</p>'
                    .'<p class="mt-1">'.e((string) ($get('_block_message') ?? '')).'</p>'
                    .'</div>'
                ))
                ->visible(fn (Get $get): bool => (bool) $get('_blocked_by_open_audit')),

            SchemaActions::make([
                Action::make('removeFromOpenAudit')
                    ->label('Quitar de la auditoría abierta')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar producto de la auditoría abierta')
                    ->modalDescription('El producto saldrá del ciclo masivo pendiente y podrá auditarse de forma individual con Auditoría Express. Esta acción queda en la traza del sistema.')
                    ->modalSubmitActionLabel('Quitar')
                    ->visible(fn (Get $get): bool => (bool) $get('_blocked_by_open_audit') && (bool) $get('_can_remove_from_open_audit'))
                    ->action(function (Get $get, Set $set): void {
                        $lineId = (int) ($get('_open_audit_line_id') ?? 0);
                        $productId = (int) ($get('product_id') ?? 0);
                        $branchId = (int) ($get('branch_id') ?? 0);

                        if ($lineId <= 0) {
                            return;
                        }

                        $line = InventoryAuditLine::query()->whereKey($lineId)->first();
                        if (! $line instanceof InventoryAuditLine) {
                            Notification::make()
                                ->title('Línea no encontrada')
                                ->body('La línea ya no está en la auditoría abierta.')
                                ->warning()
                                ->send();
                            self::hydrateProductContext($productId, $get, $set);

                            return;
                        }

                        try {
                            app(InventoryAuditApplyService::class)->removePendingLineFromOpenAudit($line, Auth::user());

                            Notification::make()
                                ->title('Producto quitado')
                                ->body('Ya puede continuar con la Auditoría Express.')
                                ->success()
                                ->send();

                            self::hydrateProductContext($productId, $get, $set);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('No se pudo quitar')
                                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                                ->danger()
                                ->send();

                            self::hydrateProductContext($productId > 0 ? $productId : (int) $line->product_id, $get, $set);
                        }
                    }),
            ])
                ->visible(fn (Get $get): bool => (bool) $get('_blocked_by_open_audit') && (bool) $get('_can_remove_from_open_audit')),

            Placeholder::make('update_form_heading')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p class="text-sm font-medium text-gray-950 dark:text-white">Aplicar actualización</p>'
                    .'<p class="text-sm text-gray-500 dark:text-gray-400">Indique la cantidad contada y, si aplica, categoría o nuevo costo.</p>'
                ))
                ->visible(fn (Get $get): bool => filled($get('product_id')) && ! (bool) $get('_blocked_by_open_audit')),

            Group::make(InventoryAuditApplyUpdateForm::fields())
                ->visible(fn (Get $get): bool => filled($get('product_id')) && ! (bool) $get('_blocked_by_open_audit')),
        ];
    }

    public static function submitAction(array $data, Action $action): void
    {
        if ((bool) ($data['_blocked_by_open_audit'] ?? false)) {
            Notification::make()
                ->title('Producto en auditoría abierta')
                ->body('Quite el producto de la auditoría abierta antes de aplicar Auditoría Express.')
                ->warning()
                ->send();

            $action->halt();
        }

        try {
            app(InventoryAuditApplyService::class)->applyExpress(
                branchId: (int) ($data['branch_id'] ?? 0),
                productId: (int) ($data['product_id'] ?? 0),
                data: [
                    'counted_quantity' => $data['counted_quantity'] ?? null,
                    'new_cost_price' => $data['new_cost_price'] ?? null,
                    'product_category_id' => $data['product_category_id'] ?? null,
                    'otp_code' => isset($data['otp_code']) ? (string) $data['otp_code'] : null,
                ],
                actor: Auth::user(),
            );
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo aplicar')
                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                ->danger()
                ->send();

            $action->halt();
        }
    }

    private static function clearProductSelection(Set $set): void
    {
        $set('product_id', null);
        $set('_blocked_by_open_audit', false);
        $set('_can_remove_from_open_audit', false);
        $set('_open_audit_id', null);
        $set('_open_audit_line_id', null);
        $set('_block_message', null);
        $set('counted_quantity', null);
        $set('new_cost_price', null);
        $set('product_category_id', null);
        $set('_branch_id', null);
        $set('_current_cost_price', null);
        $set('_applies_vat', false);
        $set('_original_product_category_id', null);
        $set('_original_quantity', null);
        $set('_product_name', null);
        $set('_branch_name', null);
        $set('otp_code', null);
    }

    private static function hydrateProductContext(int $productId, Get $get, Set $set): void
    {
        $branchId = (int) ($get('branch_id') ?? 0);

        if ($productId <= 0 || $branchId <= 0) {
            self::clearProductSelection($set);
            if ($branchId > 0) {
                $set('branch_id', $branchId);
            }

            return;
        }

        $service = app(InventoryAuditApplyService::class);
        $blockingLine = $service->findOpenAuditLineForProduct($branchId, $productId);

        if ($blockingLine instanceof InventoryAuditLine) {
            $isPending = $blockingLine->isPending();

            $set('_blocked_by_open_audit', true);
            $set('_can_remove_from_open_audit', $isPending);
            $set('_open_audit_id', (int) $blockingLine->inventory_audit_id);
            $set('_open_audit_line_id', (int) $blockingLine->getKey());
            $set('_block_message', $isPending
                ? 'Este producto forma parte de la auditoría abierta #'.$blockingLine->inventory_audit_id.'. Debe quitarlo de esa auditoría para auditarlo de forma individual.'
                : 'Este producto ya fue procesado en la auditoría abierta #'.$blockingLine->inventory_audit_id.'. Cierre esa auditoría antes de usar Auditoría Express.');
            $set('counted_quantity', null);
            $set('new_cost_price', null);
            $set('product_category_id', null);

            return;
        }

        $product = Product::query()
            ->select(['id', 'name', 'cost_price', 'product_category_id', 'applies_vat'])
            ->whereKey($productId)
            ->first();

        $inventory = Inventory::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->first(['id', 'quantity', 'cost_price']);

        if (! $product instanceof Product || ! $inventory instanceof Inventory) {
            $set('_blocked_by_open_audit', false);
            $set('_can_remove_from_open_audit', false);
            $set('_open_audit_id', null);
            $set('_open_audit_line_id', null);
            $set('_block_message', null);
            Notification::make()
                ->title('Sin inventario')
                ->body('El producto no tiene inventario en la sucursal seleccionada.')
                ->warning()
                ->send();

            return;
        }

        $systemCost = round(max(0.0, (float) ($product->cost_price ?? $inventory->cost_price ?? 0)), 2);
        $filled = InventoryAuditApplyUpdateForm::fillFromProductAndBranch(
            product: $product,
            branchId: $branchId,
            systemQuantity: round((float) $inventory->quantity, 3),
            systemCostPrice: $systemCost,
        );

        $set('_blocked_by_open_audit', false);
        $set('_can_remove_from_open_audit', false);
        $set('_open_audit_id', null);
        $set('_open_audit_line_id', null);
        $set('_block_message', null);

        foreach ($filled as $key => $value) {
            $set($key, $value);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function productSearchResults(int $branchId, string $search): array
    {
        $term = trim($search);

        $query = DB::table('products')
            ->join('inventories as inv', function ($join) use ($branchId): void {
                $join->on('inv.product_id', '=', 'products.id')
                    ->where('inv.branch_id', '=', $branchId);
            })
            ->select([
                'products.id',
                'products.name',
                'products.barcode',
                'products.sku',
                'products.active_ingredient',
                'inv.quantity as branch_qty',
            ])
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

    private static function productOptionLabel(int $branchId, int $productId): ?string
    {
        $row = DB::table('products')
            ->join('inventories as inv', function ($join) use ($branchId): void {
                $join->on('inv.product_id', '=', 'products.id')
                    ->where('inv.branch_id', '=', $branchId);
            })
            ->select([
                'products.id',
                'products.name',
                'products.barcode',
                'products.sku',
                'products.active_ingredient',
                'inv.quantity as branch_qty',
            ])
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
