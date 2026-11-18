<?php

namespace App\Services\Sales;

use App\Enums\PosHeldSaleStatus;
use App\Models\PosHeldSale;
use App\Models\Sale;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Ventas en pausa de la caja: pausar, retomar, eliminar y marcar como cobradas.
 *
 * Solo se guarda el carrito (cliente, productos, cantidades y porcentajes de descuento); los datos del
 * cobro no se guardan y los precios, la tasa y la existencia se recalculan al retomar.
 */
final class PosHeldSaleService
{
    public const ERROR_KEY = 'pos_held_sale';

    /**
     * @param  array<string, mixed>  $formData  Estado del formulario de la caja.
     * @param  array{label?: string|null, item_lines?: list<string>, estimated_total_usd?: float|null}  $summary
     */
    public function pause(User $cashier, array $formData, array $summary = [], ?string $note = null): PosHeldSale
    {
        $snapshot = self::snapshotFromFormData($formData);

        if ($snapshot['line_items'] === []) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'No hay productos en la venta para pausar.',
            ]);
        }

        $branchId = (int) ($cashier->branch_id ?? 0);
        $heldSaleId = (int) ($formData['pos_held_sale_id'] ?? 0);

        $attributes = [
            'branch_id' => $branchId > 0 ? $branchId : null,
            'status' => PosHeldSaleStatus::Paused,
            'client_id' => $snapshot['client_id'],
            'label' => filled($summary['label'] ?? null) ? mb_substr((string) $summary['label'], 0, 255) : null,
            'note' => filled($note) ? mb_substr(trim((string) $note), 0, 255) : null,
            'form_data' => $snapshot,
            'item_lines' => array_values($summary['item_lines'] ?? []),
            'items_count' => count($snapshot['line_items']),
            'estimated_total_usd' => isset($summary['estimated_total_usd']) ? round((float) $summary['estimated_total_usd'], 2) : null,
            'paused_at' => now(),
            'last_reminded_at' => null,
            'manager_notified_at' => null,
        ];

        $existing = $heldSaleId > 0
            ? PosHeldSale::query()
                ->whereKey($heldSaleId)
                ->paused()
                ->first()
            : null;

        if ($existing instanceof PosHeldSale && ! $this->canResume($cashier, $existing)) {
            $existing = null;
        }

        if ($existing instanceof PosHeldSale) {
            $existing->update($attributes);
            $heldSale = $existing;
        } else {
            $heldSale = PosHeldSale::query()->create([
                ...$attributes,
                'user_id' => $cashier->getKey(),
            ]);
        }

        AuditLogger::record(
            'pos_held_sale_paused',
            'Caja · Venta en pausa'.(filled($heldSale->label) ? ' · '.$heldSale->label : ''),
            PosHeldSale::class,
            $heldSale->getKey(),
            $heldSale->label,
            [
                'module' => 'pos_caja',
                'branch_id' => $heldSale->branch_id,
                'items_count' => $heldSale->items_count,
                'estimated_total_usd' => $heldSale->estimated_total_usd,
                'repaused' => $existing instanceof PosHeldSale,
            ],
            user: $cashier,
        );

        return $heldSale;
    }

    /**
     * Estado para precargar la caja con la venta pausada.
     *
     * @return array<string, mixed>
     */
    public function resumeFormData(User $cashier, PosHeldSale $heldSale): array
    {
        if (! $this->canResume($cashier, $heldSale)) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'Solo el cajero que pausó la venta (o un cajero de la sucursal, si es un pedido de WhatsApp) puede retomarla.',
            ]);
        }

        AuditLogger::record(
            'pos_held_sale_resumed',
            'Caja · Venta retomada'.(filled($heldSale->label) ? ' · '.$heldSale->label : ''),
            PosHeldSale::class,
            $heldSale->getKey(),
            $heldSale->label,
            [
                'module' => 'pos_caja',
                'branch_id' => $heldSale->branch_id,
                'minutes_paused' => $heldSale->minutesPaused(),
            ],
            user: $cashier,
        );

        return [
            ...(is_array($heldSale->form_data) ? $heldSale->form_data : []),
            'pos_held_sale_id' => (int) $heldSale->getKey(),
        ];
    }

    public function discard(User $actor, PosHeldSale $heldSale, string $reason): void
    {
        if (! $this->canDiscard($actor, $heldSale)) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => 'No tiene permiso para eliminar esta venta en pausa.',
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Indique el motivo para eliminar la venta en pausa.',
            ]);
        }

        $heldSale->update([
            'status' => PosHeldSaleStatus::Discarded,
            'discarded_at' => now(),
            'discarded_by_user_id' => $actor->getKey(),
            'discard_reason' => mb_substr($reason, 0, 255),
        ]);

        AuditLogger::record(
            'pos_held_sale_discarded',
            'Caja · Venta en pausa eliminada: '.mb_substr($reason, 0, 120),
            PosHeldSale::class,
            $heldSale->getKey(),
            $heldSale->label,
            [
                'module' => 'pos_caja',
                'branch_id' => $heldSale->branch_id,
                'cashier_user_id' => $heldSale->user_id,
                'reason' => $reason,
                'items_count' => $heldSale->items_count,
                'estimated_total_usd' => $heldSale->estimated_total_usd,
            ],
            user: $actor,
        );
    }

    public function markCompleted(int $heldSaleId, Sale $sale, User $cashier): void
    {
        if ($heldSaleId <= 0) {
            return;
        }

        $this->completableQuery($heldSaleId, $cashier)
            ->update([
                'status' => PosHeldSaleStatus::Completed->value,
                'completed_at' => now(),
                'sale_id' => $sale->getKey(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Cierra la venta en pausa cuando se cobró al convenio (nota de entrega, sin venta en `sales`).
     */
    public function markCompletedWithoutSale(int $heldSaleId, User $cashier): void
    {
        if ($heldSaleId <= 0) {
            return;
        }

        $this->completableQuery($heldSaleId, $cashier)
            ->update([
                'status' => PosHeldSaleStatus::Completed->value,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function canResume(User $user, PosHeldSale $heldSale): bool
    {
        if ($heldSale->status !== PosHeldSaleStatus::Paused) {
            return false;
        }

        if ((int) $heldSale->user_id === (int) $user->getKey()) {
            return true;
        }

        return $heldSale->isFromWhatsapp()
            && $heldSale->branch_id !== null
            && in_array((int) $heldSale->branch_id, PosHeldSale::cashierBranchIds($user), true);
    }

    /**
     * Venta en pausa que el cajero puede cerrar al cobrar: la suya o un pedido de WhatsApp de su sucursal.
     *
     * @return Builder<PosHeldSale>
     */
    private function completableQuery(int $heldSaleId, User $cashier): Builder
    {
        return PosHeldSale::query()
            ->whereKey($heldSaleId)
            ->paused()
            ->where(function (Builder $query) use ($cashier): void {
                $query->where('user_id', $cashier->getKey())
                    ->orWhere(fn (Builder $whatsapp): Builder => $whatsapp->whatsappForBranches($cashier));
            });
    }

    public function canDiscard(User $user, PosHeldSale $heldSale): bool
    {
        if ($heldSale->status !== PosHeldSaleStatus::Paused) {
            return false;
        }

        return PosHeldSale::query()
            ->whereKey($heldSale->getKey())
            ->visibleTo($user)
            ->exists();
    }

    /**
     * @return Collection<int, PosHeldSale>
     */
    public function pausedVisibleTo(User $user): Collection
    {
        return PosHeldSale::query()
            ->visibleTo($user)
            ->paused()
            ->with(['user:id,name,email', 'branch:id,name'])
            ->orderBy('paused_at')
            ->get();
    }

    public function pausedCountVisibleTo(User $user): int
    {
        return PosHeldSale::query()->visibleTo($user)->paused()->count();
    }

    /**
     * Pedidos de WhatsApp en pausa en las sucursales del cajero, listos para cobrar.
     */
    public function whatsappPausedCountForCashier(User $cashier): int
    {
        return PosHeldSale::query()
            ->whatsappForBranches($cashier)
            ->paused()
            ->count();
    }

    public function pausedCountForCashier(User $cashier): int
    {
        return PosHeldSale::query()
            ->where('user_id', $cashier->getKey())
            ->paused()
            ->count();
    }

    /**
     * Solo el carrito: cliente, porcentaje sobre el total y líneas (producto, cantidad y descuento).
     *
     * @param  array<string, mixed>  $formData
     * @return array{client_id: int|null, sale_discount_percent: mixed, line_items: list<array{product_id: int, quantity: float, line_discount_percent: mixed}>}
     */
    public static function snapshotFromFormData(array $formData): array
    {
        $lines = [];
        foreach ((array) ($formData['line_items'] ?? []) as $row) {
            if (! is_array($row) || ! filled($row['product_id'] ?? null) || (float) ($row['quantity'] ?? 0) <= 0) {
                continue;
            }

            $lines[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => round((float) $row['quantity'], 3),
                'line_discount_percent' => filled($row['line_discount_percent'] ?? null) ? $row['line_discount_percent'] : null,
            ];
        }

        return [
            'client_id' => filled($formData['client_id'] ?? null) ? (int) $formData['client_id'] : null,
            'sale_discount_percent' => filled($formData['sale_discount_percent'] ?? null) ? $formData['sale_discount_percent'] : null,
            'line_items' => $lines,
        ];
    }
}
