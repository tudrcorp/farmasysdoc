<?php

namespace App\Filament\Resources\InventoryAdjustments\Pages\Concerns;

use App\Filament\Resources\InventoryAdjustments\Actions\ApplyInventoryAdjustmentAction;
use App\Filament\Resources\InventoryAdjustments\Actions\QuickCreateInventoryAdjustmentProductAction;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Models\Product;
use Filament\Notifications\Notification;

trait InteractsWithInventoryAdjustmentQuickCreate
{
    public function openQuickCreateProductModalFromAdjustmentSelectSearch(string $search = '', string $repeaterKey = ''): void
    {
        $term = trim($search);
        if ($term === '') {
            return;
        }

        if (PurchaseForm::findProductForPurchaseLineSearch($term) instanceof Product) {
            return;
        }

        if (! $this->isInventoryAdjustmentModalMounted()) {
            Notification::make()
                ->title('Abra el ajuste de inventario')
                ->body('Pulse «Nuevo ajuste» antes de registrar un producto nuevo desde el buscador.')
                ->warning()
                ->send();

            return;
        }

        $this->mountAction(QuickCreateInventoryAdjustmentProductAction::NAME, [
            'search' => $term,
            'repeater_key' => trim($repeaterKey),
        ]);
    }

    public function assignCreatedProductToAdjustmentLine(Product $product, ?float $unitCostFromModal, string $repeaterKey): void
    {
        $data = $this->currentInventoryAdjustmentMountedFormData();
        if ($data === []) {
            Notification::make()
                ->title('No se pudo asignar el producto')
                ->body('El formulario de ajuste ya no está abierto. Vuelva a abrir «Nuevo ajuste».')
                ->warning()
                ->send();

            return;
        }

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $key = trim($repeaterKey);

        if ($key === '' || ! array_key_exists($key, $items)) {
            $key = $this->resolveInventoryAdjustmentRepeaterKey($items);
        }

        $cost = round(max(0.0, $unitCostFromModal ?? (float) ($product->cost_price ?? 0)), 2);
        $needsManualCost = $cost <= 0.00001;

        $existing = is_array($items[$key] ?? null) ? $items[$key] : [];

        $items[$key] = array_merge($existing, [
            'product_id' => $product->id,
            'manual_cost_required' => $needsManualCost,
            'unit_cost_snapshot' => $needsManualCost ? null : $cost,
            'product_category_id' => $product->product_category_id !== null
                ? (int) $product->product_category_id
                : null,
            'quantity' => filled($existing['quantity'] ?? null)
                ? $existing['quantity']
                : 1,
        ]);

        $patched = $this->patchInventoryAdjustmentMountedFormData([
            'items' => $items,
        ]);

        if (! $patched) {
            Notification::make()
                ->title('No se pudo asignar el producto')
                ->body('Actualice el ajuste manualmente o vuelva a abrir el formulario.')
                ->warning()
                ->send();
        }
    }

    private function isInventoryAdjustmentModalMounted(): bool
    {
        return $this->currentInventoryAdjustmentMountedFormData() !== [];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentInventoryAdjustmentMountedFormData(): array
    {
        $mounted = $this->mountedActions ?? null;
        if (! is_array($mounted)) {
            return [];
        }

        foreach ($mounted as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['name'] ?? '') !== ApplyInventoryAdjustmentAction::NAME) {
                continue;
            }

            return is_array($entry['data'] ?? null) ? $entry['data'] : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function patchInventoryAdjustmentMountedFormData(array $patch): bool
    {
        $mounted = $this->mountedActions ?? null;
        if (! is_array($mounted) || $mounted === []) {
            return false;
        }

        foreach ($mounted as $i => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['name'] ?? '') !== ApplyInventoryAdjustmentAction::NAME) {
                continue;
            }

            $current = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            $mounted[$i]['data'] = array_merge($current, $patch);
            $this->mountedActions = $mounted;

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $items
     */
    private function resolveInventoryAdjustmentRepeaterKey(array $items): string
    {
        if ($items === []) {
            return 'record-0';
        }

        $lastKey = array_key_last($items);

        return is_string($lastKey) || is_int($lastKey)
            ? (string) $lastKey
            : 'record-0';
    }
}
