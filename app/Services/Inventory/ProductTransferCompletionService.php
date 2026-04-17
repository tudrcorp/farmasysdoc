<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\ProductTransferStatus;
use App\Enums\SaleStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductTransfer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\ProductTransferStockValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductTransferCompletionService
{
    public const PAYMENT_METHOD_TRANSFER_SALE = 'traslado_sucursal';

    public function userMayMarkCompleted(?User $user, ProductTransfer $transfer): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if (! filled($user->branch_id)) {
            return false;
        }

        return (int) $user->branch_id === (int) $transfer->to_branch_id;
    }

    /**
     * Ejecuta movimiento de inventario, venta a costo en sucursal emisora y cierre del traslado.
     *
     * @throws ValidationException
     */
    public function complete(ProductTransfer $transfer, User $user): void
    {
        if ($transfer->sale_id !== null) {
            return;
        }

        if ($transfer->status !== ProductTransferStatus::InProgress) {
            throw ValidationException::withMessages([
                'data.status' => 'Solo puede completarse un traslado en estado «En proceso» (tras ser tomado por entrega). La sucursal solicitante confirma la recepción con «Marcar completado».',
            ]);
        }

        if (! $this->userMayMarkCompleted($user, $transfer)) {
            throw ValidationException::withMessages([
                'data.status' => 'Solo el personal de la sucursal destino o un administrador puede marcar el traslado como completado.',
            ]);
        }

        $transfer->load(['items' => fn ($q) => $q->orderBy('id'), 'items.product']);

        if ($transfer->items->isEmpty()) {
            throw ValidationException::withMessages([
                'data.items' => 'El traslado no tiene líneas de producto.',
            ]);
        }

        DB::transaction(function () use ($transfer, $user): void {
            $locked = ProductTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof ProductTransfer || $locked->sale_id !== null) {
                return;
            }

            if ($locked->status !== ProductTransferStatus::InProgress) {
                throw ValidationException::withMessages([
                    'data.status' => 'El traslado ya no está «En proceso»; no se puede completar en este estado.',
                ]);
            }

            ProductTransferStockValidator::assertPersistedItemsSufficientStock(
                (int) $locked->from_branch_id,
                (int) $locked->getKey(),
            );

            $actor = self::actorLabel($user);
            $fromBranchId = (int) $locked->from_branch_id;
            $toBranchId = (int) $locked->to_branch_id;

            $subtotal = 0.0;
            $saleLines = [];

            foreach ($locked->items as $item) {
                $product = $item->product;
                if (! $product instanceof Product) {
                    throw ValidationException::withMessages([
                        'data.items' => 'Producto no encontrado en una línea del traslado.',
                    ]);
                }

                $qty = (float) $item->quantity;
                if ($qty <= 0) {
                    continue;
                }

                $unitCost = (float) ($product->cost_price ?? 0);
                $lineSubtotal = round($qty * $unitCost, 2);
                $subtotal += $lineSubtotal;

                $fromInv = Inventory::query()
                    ->where('branch_id', $fromBranchId)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $fromInv instanceof Inventory) {
                    throw ValidationException::withMessages([
                        'data.items' => 'No hay inventario en origen para: '.$product->name.'.',
                    ]);
                }

                $available = (float) $fromInv->quantity - (float) $fromInv->reserved_quantity;
                if (! $fromInv->allow_negative_stock && $available + 0.0001 < $qty) {
                    throw ValidationException::withMessages([
                        'data.items' => 'Stock insuficiente en origen para: '.$product->name.'.',
                    ]);
                }

                $fromInv->quantity = round((float) $fromInv->quantity - $qty, 3);
                $fromInv->last_movement_at = now();
                $fromInv->updated_by = $actor;
                $fromInv->save();

                InventoryMovement::query()->create([
                    'product_id' => $product->id,
                    'inventory_id' => $fromInv->id,
                    'movement_type' => InventoryMovementType::Transfer,
                    'quantity' => -1 * abs($qty),
                    'unit_cost' => $unitCost,
                    'reference_type' => ProductTransfer::class,
                    'reference_id' => $locked->id,
                    'notes' => 'Salida traslado '.$locked->code,
                    'created_by' => $actor,
                ]);

                $toInv = Inventory::query()->firstOrNew([
                    'branch_id' => $toBranchId,
                    'product_id' => $product->id,
                ]);

                if (! $toInv->exists) {
                    $toInv->quantity = 0;
                    $toInv->reserved_quantity = 0;
                    $toInv->allow_negative_stock = false;
                    $toInv->created_by = $actor;
                }

                $toInv->updated_by = $actor;
                $toInv->quantity = round((float) $toInv->quantity + $qty, 3);
                $toInv->last_movement_at = now();
                $toInv->save();

                InventoryMovement::query()->create([
                    'product_id' => $product->id,
                    'inventory_id' => $toInv->id,
                    'movement_type' => InventoryMovementType::Transfer,
                    'quantity' => abs($qty),
                    'unit_cost' => $unitCost,
                    'reference_type' => ProductTransfer::class,
                    'reference_id' => $locked->id,
                    'notes' => 'Entrada traslado '.$locked->code,
                    'created_by' => $actor,
                ]);

                $lineCostTotal = round($qty * $unitCost, 2);

                $saleLines[] = [
                    'product_id' => (int) $product->id,
                    'inventory_id' => (int) $fromInv->id,
                    'quantity' => $qty,
                    'unit_price' => $unitCost,
                    'unit_cost' => $unitCost,
                    'discount_amount' => 0.0,
                    'line_subtotal' => $lineSubtotal,
                    'tax_amount' => 0.0,
                    'line_total' => $lineSubtotal,
                    'line_cost_total' => $lineCostTotal,
                    'gross_profit' => 0.0,
                    'product_name_snapshot' => $product->name,
                    'sku_snapshot' => filled($product->barcode) ? (string) $product->barcode : (string) ($product->sku ?? ''),
                ];
            }

            $subtotal = round($subtotal, 2);

            $sale = Sale::query()->create([
                'sale_number' => $this->uniqueSaleNumber(),
                'branch_id' => $fromBranchId,
                'client_id' => null,
                'status' => SaleStatus::Completed,
                'subtotal' => $subtotal,
                'tax_total' => 0.0,
                'igtf_total' => 0.0,
                'discount_total' => 0.0,
                'total' => $subtotal,
                'payment_method' => self::PAYMENT_METHOD_TRANSFER_SALE,
                'payment_usd' => $subtotal,
                'payment_ves' => 0.0,
                'bcv_ves_per_usd' => null,
                'reference' => $locked->code,
                'payment_status' => 'paid',
                'notes' => 'Venta interna por costo de inventario trasladado a otra sucursal. Traslado '.$locked->code.'.',
                'sold_at' => now(),
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            foreach ($saleLines as $line) {
                SaleItem::query()->create(array_merge(['sale_id' => $sale->id], $line));
            }

            $locked->forceFill([
                'status' => ProductTransferStatus::Completed,
                'total_transfer_cost' => $subtotal,
                'completed_by' => $actor,
                'completed_at' => now(),
                'sale_id' => $sale->id,
                'updated_by' => $actor,
            ])->save();
        });
    }

    private function uniqueSaleNumber(): string
    {
        do {
            $number = 'VTA-TRAS-'.now()->format('YmdHis').'-'.strtoupper(Str::random(5));
        } while (Sale::query()->where('sale_number', $number)->exists());

        return $number;
    }

    private static function actorLabel(User $user): string
    {
        return filled($user->email)
            ? (string) $user->email
            : (string) ($user->name ?? 'usuario');
    }
}
