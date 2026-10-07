<?php

namespace App\Services\Pricing;

use App\Models\Inventory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Asigna o quita el precio especial de un producto en una sola sucursal (fila de inventario).
 *
 * El precio calculado (costo × margen) se sigue actualizando con compras y cambios de margen;
 * mientras exista precio especial, la caja usa el especial.
 */
final class InventoryBranchSpecialPriceService
{
    public function assign(Inventory $inventory, float $priceWithoutVat, User $actor): Inventory
    {
        $this->ensureCanEdit($actor);

        $price = round($priceWithoutVat, 2);

        if ($price <= 0) {
            throw ValidationException::withMessages([
                'branch_special_price' => 'El precio especial debe ser mayor que cero.',
            ]);
        }

        $previous = $inventory->branchSpecialPriceAmount();

        $inventory->forceFill([
            'branch_special_price' => $price,
            'branch_special_price_set_at' => now(),
            'branch_special_price_set_by' => $this->actorLabel($actor),
        ])->save();

        $this->record($inventory, 'inventory_branch_special_price_set', 'asignado', $previous, $price, $actor);

        return $inventory;
    }

    public function clear(Inventory $inventory, User $actor): Inventory
    {
        $this->ensureCanEdit($actor);

        $previous = $inventory->branchSpecialPriceAmount();

        if ($previous === null) {
            return $inventory;
        }

        $inventory->forceFill([
            'branch_special_price' => null,
            'branch_special_price_set_at' => now(),
            'branch_special_price_set_by' => $this->actorLabel($actor),
        ])->save();

        $this->record($inventory, 'inventory_branch_special_price_cleared', 'quitado', $previous, null, $actor);

        return $inventory;
    }

    private function ensureCanEdit(User $actor): void
    {
        if (! $actor->canEditBranchSpecialPrice()) {
            throw ValidationException::withMessages([
                'branch_special_price' => 'No tiene permiso para asignar precios especiales por sucursal.',
            ]);
        }
    }

    private function record(Inventory $inventory, string $event, string $verb, ?float $previous, ?float $current, User $actor): void
    {
        $inventory->loadMissing(['product:id,name', 'branch:id,name']);

        $productName = $inventory->product?->name ?? 'Producto #'.$inventory->product_id;
        $branchName = $inventory->branch?->name ?? 'Sucursal #'.$inventory->branch_id;

        AuditLogger::record(
            $event,
            'Precio especial '.$verb.' · '.$productName.' · '.$branchName
                .' · antes: '.($previous !== null ? '$ '.number_format($previous, 2, ',', '.') : 'calculado')
                .' · ahora: '.($current !== null ? '$ '.number_format($current, 2, ',', '.') : 'calculado'),
            Inventory::class,
            $inventory->id,
            $productName.' · '.$branchName,
            [
                'module' => 'inventory',
                'product_id' => $inventory->product_id,
                'branch_id' => $inventory->branch_id,
                'previous_price_usd' => $previous,
                'new_price_usd' => $current,
            ],
            user: $actor,
        );
    }

    private function actorLabel(User $actor): string
    {
        return filled($actor->email) ? (string) $actor->email : (string) ($actor->name ?? 'usuario #'.$actor->getKey());
    }
}
