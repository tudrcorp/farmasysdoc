<?php

namespace App\Services\Orders;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Enums\PartnerPanelPermission;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerCompany;
use App\Models\User;
use App\Services\Partners\PartnerPanelActivityRecorder;
use App\Support\Inventory\LasDeliciasBranch;
use App\Support\Orders\OrderTotalsCalculator;
use App\Support\Orders\PartnerMedicineCharge;
use App\Support\Orders\PartnerOrderDeliverySync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea un pedido pendiente del aliado con productos del inventario de Las Delicias.
 */
final class PrincipalInventoryOrderPlacer
{
    public const MAX_LINES = 40;

    /**
     * @param  list<array{inventory_id?: mixed, quantity?: mixed}>  $lines
     */
    public function place(User $actor, array $lines): Order
    {
        $company = $actor->partnerCompany;

        if (! $actor->isPartnerCompanyUser() || ! $company instanceof PartnerCompany || ! $company->is_active) {
            throw ValidationException::withMessages([
                'order' => 'Su empresa no puede hacer pedidos en este momento.',
            ]);
        }

        if (! $actor->hasPartnerPermission(PartnerPanelPermission::PlacePrincipalInventoryOrders)) {
            throw ValidationException::withMessages([
                'order' => 'No tiene permiso para hacer pedidos del inventario.',
            ]);
        }

        $branch = LasDeliciasBranch::resolve();

        if ($branch === null) {
            throw ValidationException::withMessages([
                'order' => 'No hay una sucursal activa de Las Delicias.',
            ]);
        }

        $normalized = $this->normalizeLines($lines);

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'lines' => 'Seleccione al menos un producto e indique una cantidad.',
            ]);
        }

        if (count($normalized) > self::MAX_LINES) {
            throw ValidationException::withMessages([
                'lines' => 'Puede incluir hasta '.self::MAX_LINES.' productos por pedido.',
            ]);
        }

        return DB::transaction(function () use ($actor, $company, $branch, $normalized): Order {
            $inventories = Inventory::query()
                ->where('branch_id', $branch->id)
                ->whereIn('id', array_column($normalized, 'inventory_id'))
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->with('product')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($inventories->count() !== count($normalized)) {
                throw ValidationException::withMessages([
                    'lines' => 'Algún producto ya no está disponible en Las Delicias. Actualice el listado e intente de nuevo.',
                ]);
            }

            $actorName = filled($actor->email) ? (string) $actor->email : (string) $actor->name;
            $profit = $company->medicineDispatchProfitPercent();

            $order = Order::query()->create([
                'order_number' => 'TMP-'.Str::lower((string) Str::ulid()),
                'client_id' => null,
                'branch_id' => $branch->id,
                'partner_company_id' => $company->getKey(),
                'status' => OrderStatus::Pending,
                'convenio_type' => ConvenioType::Particular,
                'is_wholesale' => false,
                'subtotal' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => 0,
                'notes' => 'Pedido desde el inventario de Las Delicias.',
                'created_by' => $actorName,
                'updated_by' => $actorName,
            ]);

            foreach ($normalized as $line) {
                /** @var Inventory $inventory */
                $inventory = $inventories->get($line['inventory_id']);
                $product = $inventory->product;

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'lines' => 'Algún producto ya no está disponible en Las Delicias. Actualice el listado e intente de nuevo.',
                    ]);
                }

                OrderItem::query()->create(array_merge(
                    OrderTotalsCalculator::lineAmounts($product, $line['quantity'], $profit),
                    [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'inventory_id' => $inventory->id,
                        'quantity' => $line['quantity'],
                    ],
                ));
            }

            $order->assignCanonicalOrderNumber();
            $order->recalculateTotalsFromItems();
            PartnerMedicineCharge::forCompany($company)->rememberOn($order);
            PartnerOrderDeliverySync::sync($order, $actor);

            $order->refresh();

            PartnerPanelActivityRecorder::record(
                (int) $company->getKey(),
                'principal_inventory_order_created',
                'Hizo el pedido '.$order->order_number.' desde el inventario de Las Delicias ('.count($normalized).' productos).',
                $actor,
                $order,
                (string) $order->order_number,
                ['items' => count($normalized)],
            );

            return $order;
        });
    }

    /**
     * @param  list<array{inventory_id?: mixed, quantity?: mixed}>  $lines
     * @return list<array{inventory_id: int, quantity: float}>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $inventoryId = (int) ($line['inventory_id'] ?? 0);
            $quantity = $line['quantity'] ?? null;

            if ($inventoryId <= 0 || ! is_numeric($quantity)) {
                continue;
            }

            $quantity = round((float) $quantity, 3);

            if ($quantity < 1 || $quantity > 9999) {
                throw ValidationException::withMessages([
                    'lines' => 'La cantidad de cada producto debe estar entre 1 y 9999.',
                ]);
            }

            $normalized[$inventoryId] = [
                'inventory_id' => $inventoryId,
                'quantity' => $quantity,
            ];
        }

        return array_values($normalized);
    }
}
