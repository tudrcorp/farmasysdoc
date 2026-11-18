<?php

namespace App\Services\WhatsappAgent;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Enums\PosHeldSaleSource;
use App\Enums\PosHeldSaleStatus;
use App\Enums\WhatsappConversationStatus;
use App\Enums\WhatsappFulfillmentType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosHeldSale;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationItem;
use App\Services\Audit\AuditLogger;
use App\Support\Orders\OrderTotalsCalculator;
use App\Support\Sales\ProductUnitPricingForBranch;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Convierte la conversación en lo que gestiona la farmacia:
 * - Venta en pausa en la caja de la sucursal (la crea el asistente al confirmar el pedido o el analista con «Retiro en tienda»;
 *   la retoma cualquier cajero de la sucursal).
 * - Delivery → pedido (`orders`), igual que los pedidos de la tienda web.
 */
final class WhatsappConversationConverter
{
    public const CREATED_BY = 'whatsapp:analista';

    public function toPickupHeldSale(WhatsappConversation $conversation, Branch $branch, User $analyst): PosHeldSale
    {
        [$clientId, $items] = $this->assertConvertible($conversation);

        $heldSale = DB::transaction(function () use ($conversation, $branch, $analyst, $clientId, $items): PosHeldSale {
            $heldSale = $this->upsertHeldSale($conversation, $branch, $clientId, $items, WhatsappFulfillmentType::Pickup, $analyst);

            $conversation->forceFill([
                'status' => WhatsappConversationStatus::Converted,
                'assigned_user_id' => $conversation->assigned_user_id ?? $analyst->getKey(),
                'closed_at' => now(),
                'closed_by_user_id' => $analyst->getKey(),
            ])->save();

            return $heldSale;
        });

        AuditLogger::record(
            'whatsapp_conversation_converted_pickup',
            'WhatsApp · Venta en pausa para retiro en '.$branch->name,
            PosHeldSale::class,
            $heldSale->getKey(),
            $heldSale->label,
            ['module' => 'whatsapp_agent', 'conversation_id' => $conversation->getKey(), 'branch_id' => $branch->getKey()],
            user: $analyst,
        );

        if ($heldSale->wasRecentlyCreated) {
            $this->notifyBranchCashiers($heldSale, $branch);
        }

        return $heldSale;
    }

    /**
     * Crea la venta en pausa del pedido de WhatsApp o, si ya existe y sigue en pausa, la actualiza
     * (así el asistente y el analista nunca duplican el pedido en la caja).
     *
     * @param  Collection<int, WhatsappConversationItem>  $items  Solo líneas con producto del catálogo.
     * @param  list<string>  $pendingToSource  Productos que el equipo debe conseguir (no están en inventario).
     */
    public function upsertHeldSale(
        WhatsappConversation $conversation,
        Branch $branch,
        ?int $clientId,
        Collection $items,
        WhatsappFulfillmentType $fulfillment,
        ?User $actor = null,
        array $pendingToSource = [],
    ): PosHeldSale {
        $lines = [];
        $itemLines = [];
        $estimated = 0.0;

        foreach ($items as $item) {
            $product = $item->product;
            $quantity = round((float) $item->quantity, 3);
            $inventory = Inventory::query()->where('branch_id', $branch->getKey())->where('product_id', $product->getKey())->first();
            $pricing = ProductUnitPricingForBranch::resolve($product, (int) $branch->getKey(), $inventory);

            $lines[] = [
                'product_id' => (int) $product->getKey(),
                'quantity' => $quantity,
                'line_discount_percent' => null,
            ];
            $itemLines[] = self::quantityText($quantity).' × '.$product->name;
            $estimated += $quantity * (float) $pricing['unit_final'];
        }

        foreach ($pendingToSource as $name) {
            $itemLines[] = 'Por conseguir: '.$name;
        }

        $note = collect([
            $fulfillment === WhatsappFulfillmentType::Delivery ? 'Delivery' : 'Retiro en tienda',
            filled($conversation->payment_method_note) ? 'Pago: '.$conversation->payment_method_note : null,
            $pendingToSource !== [] ? count($pendingToSource).' por conseguir' : null,
        ])->filter()->implode(' · ');

        $attributes = [
            'branch_id' => $branch->getKey(),
            'client_id' => $clientId,
            'label' => mb_substr('WhatsApp · '.$conversation->contact?->displayName(), 0, 255),
            'note' => mb_substr($note, 0, 255),
            'form_data' => [
                'client_id' => $clientId,
                'sale_discount_percent' => null,
                'line_items' => $lines,
            ],
            'item_lines' => $itemLines,
            'items_count' => count($lines),
            'estimated_total_usd' => round($estimated, 2),
        ];

        $existing = $conversation->pos_held_sale_id !== null
            ? PosHeldSale::query()->whereKey($conversation->pos_held_sale_id)->paused()->first()
            : null;

        if ($existing instanceof PosHeldSale) {
            $existing->update($attributes);
            $heldSale = $existing;
        } else {
            $heldSale = PosHeldSale::query()->create([
                ...$attributes,
                'user_id' => $actor?->getKey(),
                'status' => PosHeldSaleStatus::Paused,
                'source' => PosHeldSaleSource::Whatsapp,
                'whatsapp_conversation_id' => $conversation->getKey(),
                'paused_at' => now(),
            ]);
        }

        $conversation->forceFill([
            'branch_id' => $branch->getKey(),
            'fulfillment_type' => $fulfillment,
            'pos_held_sale_id' => $heldSale->getKey(),
        ])->save();

        return $heldSale;
    }

    public function toDeliveryOrder(WhatsappConversation $conversation, Branch $branch, User $analyst): Order
    {
        [$clientId, $items] = $this->assertConvertible($conversation);

        if (blank($conversation->delivery_address) && $conversation->delivery_latitude === null) {
            throw ValidationException::withMessages([
                'delivery_address' => 'Falta la dirección de entrega del cliente.',
            ]);
        }

        $contact = $conversation->contact;
        $itemStates = $items
            ->map(fn (WhatsappConversationItem $item): array => [
                'product_id' => (int) $item->product_id,
                'quantity' => (float) $item->quantity,
            ])
            ->values()
            ->all();

        $order = DB::transaction(function () use ($conversation, $branch, $analyst, $clientId, $itemStates, $contact): Order {
            $totals = OrderTotalsCalculator::aggregateFromItemStates($itemStates);
            $actor = filled($analyst->email) ? (string) $analyst->email : self::CREATED_BY;

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'client_id' => $clientId,
                'branch_id' => $branch->getKey(),
                'status' => OrderStatus::Pending,
                'convenio_type' => ConvenioType::Particular,
                'delivery_recipient_name' => $conversation->delivery_recipient_name ?: $contact?->displayName(),
                'delivery_recipient_document' => $contact?->formattedDocument(),
                'delivery_phone' => $conversation->delivery_recipient_phone ?: $contact?->localPhone(),
                'delivery_address' => $conversation->delivery_address ?: 'Ubicación enviada por WhatsApp',
                'delivery_latitude' => $conversation->delivery_latitude,
                'delivery_longitude' => $conversation->delivery_longitude,
                'delivery_notes' => $conversation->delivery_reference,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $totals['discount_total'],
                'total' => $totals['total'],
                'notes' => $this->buildNotes($conversation),
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            foreach ($itemStates as $state) {
                $product = Product::query()->find($state['product_id']);

                if (! $product instanceof Product) {
                    continue;
                }

                $amounts = OrderTotalsCalculator::lineAmounts($product, $state['quantity']);

                OrderItem::query()->create([
                    'order_id' => $order->getKey(),
                    'product_id' => $product->getKey(),
                    'quantity' => $state['quantity'],
                    'unit_price' => $amounts['unit_price'],
                    'discount_amount' => $amounts['discount_amount'],
                    'line_subtotal' => $amounts['line_subtotal'],
                    'tax_amount' => $amounts['tax_amount'],
                    'line_total' => $amounts['line_total'],
                    'product_name_snapshot' => $amounts['product_name_snapshot'],
                    'sku_snapshot' => $amounts['sku_snapshot'],
                ]);
            }

            $conversation->forceFill([
                'status' => WhatsappConversationStatus::Converted,
                'branch_id' => $branch->getKey(),
                'fulfillment_type' => WhatsappFulfillmentType::Delivery,
                'order_id' => $order->getKey(),
                'assigned_user_id' => $conversation->assigned_user_id ?? $analyst->getKey(),
                'closed_at' => now(),
                'closed_by_user_id' => $analyst->getKey(),
            ])->save();

            return $order;
        });

        AuditLogger::record(
            'whatsapp_conversation_converted_delivery',
            'WhatsApp · Pedido con delivery '.$order->order_number,
            Order::class,
            $order->getKey(),
            $order->order_number,
            ['module' => 'whatsapp_agent', 'conversation_id' => $conversation->getKey(), 'branch_id' => $branch->getKey()],
            user: $analyst,
        );

        return $order;
    }

    /**
     * @return array{0: int, 1: Collection<int, WhatsappConversationItem>}
     */
    private function assertConvertible(WhatsappConversation $conversation): array
    {
        $conversation->loadMissing(['contact', 'items.product']);

        if (! $conversation->isOpen()) {
            throw ValidationException::withMessages([
                'conversation' => 'Esta conversación ya se convirtió en pedido o está cerrada.',
            ]);
        }

        $clientId = $conversation->contact?->client_id;

        if ($clientId === null) {
            throw ValidationException::withMessages([
                'client' => 'El cliente aún no está registrado. Registre su nombre y cédula antes de crear el pedido.',
            ]);
        }

        $items = $conversation->items->filter(fn (WhatsappConversationItem $item): bool => $item->product instanceof Product && (float) $item->quantity > 0)->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'El pedido no tiene productos del catálogo. Asigne el producto a cada línea antes de continuar.',
            ]);
        }

        return [(int) $clientId, $items];
    }

    public function notifyBranchCashiers(PosHeldSale $heldSale, Branch $branch): void
    {
        $cashiers = User::query()
            ->get()
            ->filter(fn (User $user): bool => $user->isCashier()
                && in_array((int) $branch->getKey(), PosHeldSale::cashierBranchIds($user), true));

        foreach ($cashiers as $cashier) {
            Notification::make()
                ->title('Pedido de WhatsApp para retirar en tienda')
                ->body(($heldSale->label ?? 'Cliente').' · '.$heldSale->items_count.' producto(s). Lo encuentra en «Ventas en pausa» de la caja.')
                ->info()
                ->icon('heroicon-o-shopping-bag')
                ->sendToDatabase($cashier);
        }
    }

    private function buildNotes(WhatsappConversation $conversation): string
    {
        $lines = ['Pedido por WhatsApp · Delivery'];

        if (filled($conversation->payment_method_note)) {
            $lines[] = 'Pago: '.$conversation->payment_method_note;
        }

        if (filled($conversation->summary)) {
            $lines[] = 'Resumen: '.mb_strimwidth((string) $conversation->summary, 0, 800, '…');
        }

        return implode("\n", $lines);
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'WA-'.now()->format('ymd').'-'.random_int(1000, 9999);
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }

    private static function quantityText(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
