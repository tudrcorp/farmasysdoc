<?php

namespace App\Services\Purchases;

use App\Enums\SupplierReturnEventType;
use App\Enums\SupplierReturnStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnAttachment;
use App\Models\SupplierReturnEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Única vía para registrar devoluciones de mercancía recibida de más y moverlas de estado.
 *
 * Por revisar → Aprobada → Devuelta, o Por revisar → Rechazada. Solo un Administrador aprueba o rechaza;
 * la entrega al proveedor la marca un Administrador o un usuario de la sucursal. No toca inventario:
 * la mercancía de más nunca entró al stock. Cada transición bloquea la fila y deja bitácora.
 */
final class SupplierReturnWorkflow
{
    public function __construct(private SupplierReturnNotifier $notifier) {}

    /**
     * @param  array<string, mixed>  $data  branch_id, supplier_id, purchase_id, received_on, notes
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, string>  $attachments  ruta => nombre original
     *
     * @throws SupplierReturnException
     */
    public function register(array $data, array $items, array $attachments, User $actor): SupplierReturn
    {
        $return = DB::transaction(function () use ($data, $items, $attachments, $actor): SupplierReturn {
            $return = new SupplierReturn;
            $return->fill($data);
            $this->assertHeader($return, $actor);

            $normalizedItems = $this->normalizeItems($items, $return);

            $return->forceFill([
                'status' => SupplierReturnStatus::PendingReview,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();

            $return->forceFill(['number' => SupplierReturn::formatNumber($return->getKey())])->save();

            $return->items()->createMany($normalizedItems);
            $this->syncAttachments($return, $attachments, SupplierReturnAttachment::STAGE_REGISTRATION, $actor);
            $this->log($return, SupplierReturnEventType::Created, null, SupplierReturnStatus::PendingReview, $actor);

            return $return;
        });

        $this->notifier->notify($return, SupplierReturnEventType::Created, $actor);

        return $return;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, string>  $attachments
     *
     * @throws SupplierReturnException
     */
    public function update(SupplierReturn $return, array $data, array $items, array $attachments, User $actor): SupplierReturn
    {
        return $this->transition($return, function (SupplierReturn $locked) use ($data, $items, $attachments, $actor): void {
            if ($locked->status !== SupplierReturnStatus::PendingReview) {
                throw new SupplierReturnException('Solo se editan devoluciones que están por revisar.');
            }

            $this->assertCanActOnBranch($actor, (int) $locked->branch_id);

            $locked->fill($data);
            $this->assertHeader($locked, $actor);
            $normalizedItems = $this->normalizeItems($items, $locked);

            $locked->forceFill(['updated_by' => $actor->getKey()])->save();

            $locked->items()->delete();
            $locked->items()->createMany($normalizedItems);
            $this->syncAttachments($locked, $attachments, SupplierReturnAttachment::STAGE_REGISTRATION, $actor);

            $this->log($locked, SupplierReturnEventType::Updated, null, null, $actor);
        });
    }

    /**
     * @throws SupplierReturnException
     */
    public function approve(SupplierReturn $return, User $actor, ?string $notes = null): SupplierReturn
    {
        $this->assertAdministrator($actor);

        $return = $this->transition($return, function (SupplierReturn $locked) use ($actor, $notes): void {
            $this->assertStatus($locked, SupplierReturnStatus::PendingReview, 'Solo se aprueban devoluciones por revisar.');

            $locked->forceFill([
                'status' => SupplierReturnStatus::Approved,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_notes' => filled($notes) ? trim((string) $notes) : null,
            ])->save();

            $this->log($locked, SupplierReturnEventType::Approved, SupplierReturnStatus::PendingReview, SupplierReturnStatus::Approved, $actor, $notes);
        });

        $this->notifier->notify($return, SupplierReturnEventType::Approved, $actor);

        return $return;
    }

    /**
     * @throws SupplierReturnException
     */
    public function reject(SupplierReturn $return, User $actor, string $reason): SupplierReturn
    {
        $this->assertAdministrator($actor);

        if (blank(trim($reason))) {
            throw new SupplierReturnException('Indique el motivo del rechazo.');
        }

        $return = $this->transition($return, function (SupplierReturn $locked) use ($actor, $reason): void {
            $this->assertStatus($locked, SupplierReturnStatus::PendingReview, 'Solo se rechazan devoluciones por revisar.');

            $locked->forceFill([
                'status' => SupplierReturnStatus::Rejected,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => trim($reason),
            ])->save();

            $this->log($locked, SupplierReturnEventType::Rejected, SupplierReturnStatus::PendingReview, SupplierReturnStatus::Rejected, $actor, trim($reason));
        });

        $this->notifier->notify($return, SupplierReturnEventType::Rejected, $actor);

        return $return;
    }

    /**
     * @param  array<string, string>  $attachments
     *
     * @throws SupplierReturnException
     */
    public function markReturned(SupplierReturn $return, User $actor, CarbonImmutable $returnedOn, ?string $notes, array $attachments = []): SupplierReturn
    {
        $return = $this->transition($return, function (SupplierReturn $locked) use ($actor, $returnedOn, $notes, $attachments): void {
            $this->assertStatus($locked, SupplierReturnStatus::Approved, 'Solo se marcan como devueltas las devoluciones aprobadas.');
            $this->assertCanActOnBranch($actor, (int) $locked->branch_id);

            if ($returnedOn->isFuture()) {
                throw new SupplierReturnException('La fecha de entrega al proveedor no puede ser futura.');
            }

            if ($returnedOn->lt($locked->received_on)) {
                throw new SupplierReturnException('La fecha de entrega no puede ser anterior a la fecha en que se recibió la mercancía.');
            }

            $locked->forceFill([
                'status' => SupplierReturnStatus::Returned,
                'returned_by' => $actor->getKey(),
                'returned_at' => now(),
                'returned_on' => $returnedOn,
                'return_notes' => filled($notes) ? trim((string) $notes) : null,
            ])->save();

            $this->syncAttachments($locked, $attachments, SupplierReturnAttachment::STAGE_RETURN, $actor, keepExisting: true);
            $this->log($locked, SupplierReturnEventType::Returned, SupplierReturnStatus::Approved, SupplierReturnStatus::Returned, $actor, $notes);
        });

        $this->notifier->notify($return, SupplierReturnEventType::Returned, $actor);

        return $return;
    }

    public function canMarkReturned(User $actor, SupplierReturn $return): bool
    {
        return $return->status === SupplierReturnStatus::Approved && $this->actsOnBranch($actor, (int) $return->branch_id);
    }

    public function canEdit(User $actor, SupplierReturn $return): bool
    {
        return $return->status === SupplierReturnStatus::PendingReview && $this->actsOnBranch($actor, (int) $return->branch_id);
    }

    /**
     * @throws SupplierReturnException
     */
    private function assertHeader(SupplierReturn $return, User $actor): void
    {
        if (blank($return->branch_id) || blank($return->supplier_id) || blank($return->received_on)) {
            throw new SupplierReturnException('Sucursal, proveedor y fecha de recepción son obligatorios.');
        }

        if (blank(trim((string) $return->notes))) {
            throw new SupplierReturnException('Escriba la nota de la devolución.');
        }

        if (CarbonImmutable::parse($return->received_on)->isFuture()) {
            throw new SupplierReturnException('La fecha de recepción no puede ser futura.');
        }

        $this->assertCanActOnBranch($actor, (int) $return->branch_id);

        if (! Supplier::query()->whereKey($return->supplier_id)->exists()) {
            throw new SupplierReturnException('El proveedor seleccionado no existe.');
        }

        if (filled($return->purchase_id)) {
            $purchase = Purchase::query()->find($return->purchase_id);

            if (! $purchase instanceof Purchase || (int) $purchase->supplier_id !== (int) $return->supplier_id) {
                throw new SupplierReturnException('La compra seleccionada no pertenece a ese proveedor.');
            }

            if ((int) $purchase->branch_id !== (int) $return->branch_id) {
                throw new SupplierReturnException('La compra seleccionada es de otra sucursal.');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     *
     * @throws SupplierReturnException
     */
    private function normalizeItems(array $items, SupplierReturn $return): array
    {
        $items = array_values(array_filter($items, fn (mixed $item): bool => is_array($item)
            && (filled($item['barcode'] ?? null) || filled($item['product_name'] ?? null))));

        if ($items === []) {
            throw new SupplierReturnException('Agregue al menos un producto a la devolución.');
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            $barcode = filled($item['barcode'] ?? null) ? trim((string) $item['barcode']) : null;
            $productId = filled($item['product_id'] ?? null) ? (int) $item['product_id'] : null;

            $barcodeMatches = $barcode !== null ? Product::matchingBarcode($barcode) : null;
            $product = $productId !== null
                ? Product::query()->find($productId)
                : ($barcodeMatches?->count() === 1 ? $barcodeMatches->first() : null);

            $name = trim((string) ($item['product_name'] ?? '')) ?: (string) ($product?->name ?? '');

            if ($name === '') {
                throw new SupplierReturnException('Indique el nombre del producto de la línea '.($index + 1).'.');
            }

            $invoiced = round((float) ($item['quantity_invoiced'] ?? 0), 3);
            $received = round((float) ($item['quantity_received'] ?? 0), 3);

            if ($invoiced < 0 || $received <= 0) {
                throw new SupplierReturnException("Revise las cantidades de «{$name}»: la facturada no puede ser negativa y la recibida debe ser mayor que cero.");
            }

            if ($received <= $invoiced) {
                throw new SupplierReturnException("En «{$name}» la cantidad recibida debe ser mayor que la facturada: no hay mercancía de más que devolver.");
            }

            $purchaseItemId = filled($item['purchase_item_id'] ?? null) ? (int) $item['purchase_item_id'] : null;

            if ($purchaseItemId !== null && ! PurchaseItem::query()->whereKey($purchaseItemId)->where('purchase_id', $return->purchase_id)->exists()) {
                $purchaseItemId = null;
            }

            $normalized[] = [
                'product_id' => $product?->getKey(),
                'purchase_item_id' => $purchaseItemId,
                'barcode' => $barcode ?? (filled($product?->barcode) ? trim((string) $product->barcode) : null),
                'product_name' => $name,
                'quantity_invoiced' => $invoiced,
                'quantity_received' => $received,
                'quantity_excess' => round($received - $invoiced, 3),
                'sort' => $index,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $attachments  ruta => nombre original
     */
    private function syncAttachments(SupplierReturn $return, array $attachments, string $stage, User $actor, bool $keepExisting = false): void
    {
        $existing = $return->attachments()->where('stage', $stage)->get();

        if (! $keepExisting) {
            $existing
                ->reject(fn (SupplierReturnAttachment $attachment): bool => array_key_exists((string) $attachment->file_path, $attachments))
                ->each(fn (SupplierReturnAttachment $attachment) => $attachment->delete());
        }

        $known = $existing->pluck('file_path')->map(fn ($path): string => (string) $path)->all();

        foreach ($attachments as $path => $name) {
            if ($path === '' || str_contains($path, '..') || in_array($path, $known, true)) {
                continue;
            }

            $return->attachments()->create([
                'stage' => $stage,
                'file_path' => $path,
                'original_name' => $name ?: basename($path),
                'uploaded_by' => $actor->getKey(),
            ]);
        }
    }

    /**
     * @param  Closure(SupplierReturn): void  $callback
     */
    private function transition(SupplierReturn $return, Closure $callback): SupplierReturn
    {
        return DB::transaction(function () use ($return, $callback): SupplierReturn {
            /** @var SupplierReturn $locked */
            $locked = SupplierReturn::query()->whereKey($return->getKey())->lockForUpdate()->firstOrFail();

            $callback($locked);

            return $locked;
        });
    }

    private function assertStatus(SupplierReturn $return, SupplierReturnStatus $expected, string $message): void
    {
        if ($return->status !== $expected) {
            throw new SupplierReturnException($message.' Estado actual: '.mb_strtolower($return->status->label()).'.');
        }
    }

    private function assertAdministrator(User $actor): void
    {
        if (! $actor->isAdministrator()) {
            throw new SupplierReturnException('Solo un Administrador puede aprobar o rechazar devoluciones.');
        }
    }

    private function assertCanActOnBranch(User $actor, int $branchId): void
    {
        if (! $this->actsOnBranch($actor, $branchId)) {
            throw new SupplierReturnException('No tiene acceso a la sucursal de esta devolución.');
        }
    }

    private function actsOnBranch(User $actor, int $branchId): bool
    {
        if ($actor->isAdministrator()) {
            return true;
        }

        return in_array($branchId, array_map('intval', $actor->restrictedBranchIdsForQueries()), true);
    }

    private function log(SupplierReturn $return, SupplierReturnEventType $type, ?SupplierReturnStatus $from, ?SupplierReturnStatus $to, ?User $actor, ?string $notes = null): void
    {
        SupplierReturnEvent::query()->create([
            'supplier_return_id' => $return->getKey(),
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor?->getKey(),
            'notes' => filled($notes) ? trim((string) $notes) : null,
        ]);
    }
}
