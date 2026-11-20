<?php

namespace App\Services\Orders;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderAcceptance;
use App\Models\OrderAcceptanceItem;
use App\Models\OrderItem;
use App\Models\OrderService;
use App\Models\Product;
use App\Models\User;
use App\Services\Dolar\DolarApiDolaresService;
use App\Services\Inventory\FefoLotSaleDispatchService;
use App\Support\Finance\BcvRate;
use App\Support\Inventory\LasDeliciasBranch;
use App\Support\Orders\OrderTotalsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OrderAcceptanceService
{
    public function __construct(
        private readonly DolarApiDolaresService $rates,
        private readonly FefoLotSaleDispatchService $fefo,
    ) {}

    /**
     * @param  array<int, array{accepted: bool, rejection_note?: ?string}>  $decisions
     */
    public function accept(Order $order, array $decisions, User $actor): OrderAcceptance
    {
        $rate = BcvRate::truncateOrNull($this->rates->getOfficialUsdToVesRate());

        if ($rate === null) {
            throw ValidationException::withMessages([
                'rate' => 'No se pudo consultar la tasa BCV. Intente de nuevo en unos minutos.',
            ]);
        }

        return DB::transaction(function () use ($order, $decisions, $actor, $rate): OrderAcceptance {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->acceptance()->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido ya fue aceptado.',
                ]);
            }

            if (! in_array($locked->status, [OrderStatus::Pending, OrderStatus::InProgress], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Solo se aceptan pedidos pendientes o en proceso.',
                ]);
            }

            $branch = LasDeliciasBranch::resolve();

            $locked->load(['items.product', 'serviceOrder']);

            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'El pedido no tiene productos.',
                ]);
            }

            $actorName = trim((string) ($actor->name ?? ''));

            if ($actorName === '') {
                $actorName = (string) ($actor->email ?? 'sistema');
            }

            $prepared = [];
            $acceptedUsd = 0.0;
            $acceptedVes = 0.0;

            foreach ($locked->items as $item) {
                if (! $item instanceof OrderItem) {
                    continue;
                }

                $decision = $decisions[(int) $item->getKey()] ?? ['accepted' => false, 'rejection_note' => null];
                $accepted = (bool) ($decision['accepted'] ?? false);
                $note = trim((string) ($decision['rejection_note'] ?? ''));
                $name = filled($item->product_name_snapshot)
                    ? (string) $item->product_name_snapshot
                    : (string) ($item->product?->name ?? 'Producto');
                $quantity = round((float) $item->quantity, 3);
                $product = $item->product instanceof Product ? $item->product : null;
                $inventory = null;
                $onHand = 0.0;

                if ($product instanceof Product && $branch !== null) {
                    $inventory = Inventory::query()
                        ->where('branch_id', $branch->getKey())
                        ->where('product_id', $product->getKey())
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory instanceof Inventory && $accepted && $quantity > 0) {
                        $inventory = new Inventory([
                            'branch_id' => $branch->getKey(),
                            'product_id' => $product->getKey(),
                            'quantity' => 0,
                            'reserved_quantity' => 0,
                            'allow_negative_stock' => false,
                            'created_by' => $actorName,
                            'updated_by' => $actorName,
                        ]);
                        $inventory->save();

                        $inventory = Inventory::query()
                            ->whereKey($inventory->getKey())
                            ->lockForUpdate()
                            ->first();
                    }

                    $onHand = $inventory instanceof Inventory ? round((float) $inventory->quantity, 3) : 0.0;
                }

                $priced = OrderTotalsCalculator::acceptanceSaleAmounts(
                    $item,
                    $branch?->getKey(),
                    $inventory,
                );
                $unit = $priced['unit_price_usd'];
                $lineUsd = $priced['line_total_usd'];

                if (! $accepted && $note === '') {
                    throw ValidationException::withMessages([
                        'items' => 'Indique por qué no se envía «'.$name.'».',
                    ]);
                }

                $lineVes = round($lineUsd * $rate, 2);

                if ($accepted) {
                    $acceptedUsd = round($acceptedUsd + $lineUsd, 2);
                    $acceptedVes = round($acceptedVes + $lineVes, 2);
                }

                $prepared[] = [
                    'item' => $item,
                    'product' => $product,
                    'inventory' => $inventory,
                    'name' => $name,
                    'accepted' => $accepted,
                    'note' => $accepted ? null : Str::limit($note, 1000, ''),
                    'quantity' => $quantity,
                    'on_hand' => $onHand,
                    'unit' => $unit,
                    'line_usd' => $lineUsd,
                    'line_ves' => $lineVes,
                ];
            }

            $acceptance = OrderAcceptance::query()->create([
                'order_id' => $locked->getKey(),
                'partner_company_id' => $locked->partner_company_id,
                'accepted_by' => $actor->getKey(),
                'accepted_by_name' => $actorName,
                'accepted_at' => now(),
                'bcv_ves_per_usd' => $rate,
                'accepted_total_usd' => $acceptedUsd,
                'accepted_total_ves' => $acceptedVes,
                'delivery_note_number' => 'TMP-'.Str::lower((string) Str::ulid()),
            ]);

            $acceptance->forceFill([
                'delivery_note_number' => OrderAcceptance::numberFor((int) $acceptance->getKey()),
            ])->save();

            foreach ($prepared as $row) {
                OrderAcceptanceItem::query()->create([
                    'order_acceptance_id' => $acceptance->getKey(),
                    'order_item_id' => $row['item']->getKey(),
                    'product_id' => $row['product']?->getKey(),
                    'product_name' => $row['name'],
                    'barcode' => filled($row['product']?->barcode) ? trim((string) $row['product']->barcode) : null,
                    'sku' => filled($row['item']->sku_snapshot) ? (string) $row['item']->sku_snapshot : ($row['product']?->sku ?: null),
                    'quantity_ordered' => $row['quantity'],
                    'quantity_on_hand' => $row['on_hand'],
                    'accepted' => $row['accepted'],
                    'rejection_note' => $row['note'],
                    'unit_price_usd' => $row['unit'],
                    'line_total_usd' => $row['line_usd'],
                    'line_total_ves' => $row['line_ves'],
                ]);

                if (! $row['accepted'] || $branch === null || ! $row['product'] instanceof Product || ! $row['inventory'] instanceof Inventory) {
                    continue;
                }

                $inventory = $row['inventory'];
                $inventory->quantity = round((float) $inventory->quantity - $row['quantity'], 3);
                $inventory->last_movement_at = now();
                $inventory->updated_by = $actorName;
                $inventory->save();

                $this->fefo->dispatchLine(
                    (int) $branch->getKey(),
                    $row['product'],
                    $row['quantity'],
                    $inventory,
                    $acceptance,
                    'Nota de entrega '.$acceptance->delivery_note_number,
                    $actorName,
                    InventoryMovementType::PartnerDispatch,
                );
            }

            $locked->forceFill([
                'status' => OrderStatus::Accepted,
                'updated_by' => $actorName,
            ])->save();

            $serviceOrder = $locked->serviceOrder;

            if ($serviceOrder instanceof OrderService && ! in_array(strtolower((string) $serviceOrder->status), ['finalizada', 'cancelada'], true)) {
                $serviceOrder->forceFill([
                    'status' => 'aprobada',
                    'updated_by' => $actorName,
                ])->save();
            }

            return $acceptance->load('items');
        });
    }
}
