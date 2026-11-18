<?php

namespace App\Services\WhatsappAgent;

use App\Enums\WhatsappFulfillmentType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\PosHeldSale;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cuando el cliente confirma el pedido, el asistente lo deja de una vez en «Ventas en pausa» de la caja
 * de la sucursal (retiro o la que despacha el delivery) y avisa al equipo para verificar el pago y facturar.
 */
final class WhatsappOrderConfirmer
{
    public function __construct(
        private readonly WhatsappConversationConverter $converter,
        private readonly WhatsappConversationEscalator $escalator,
    ) {}

    /**
     * @return array{held_sale_id: int, branch: string, items_in_register: int, to_source: list<string>, updated: bool}
     */
    public function confirm(
        WhatsappConversation $conversation,
        WhatsappFulfillmentType $fulfillment,
        ?int $branchId,
        ?string $paymentMethod,
        string $summary,
    ): array {
        $conversation->loadMissing(['contact', 'items.product']);

        if (! $conversation->isOpen()) {
            throw ValidationException::withMessages(['conversation' => 'La conversación ya está cerrada.']);
        }

        $clientId = $conversation->contact?->client_id;

        if ($clientId === null) {
            throw ValidationException::withMessages([
                'client' => 'El cliente aún no está registrado. Pídele su nombre, apellido y cédula y usa registrar_cliente antes de confirmar.',
            ]);
        }

        $withProduct = $conversation->items->filter(fn (WhatsappConversationItem $item): bool => $item->product !== null && (float) $item->quantity > 0)->values();
        $toSource = $conversation->items->filter(fn (WhatsappConversationItem $item): bool => $item->product === null)->pluck('requested_name')->values()->all();

        if ($withProduct->isEmpty() && $toSource === []) {
            throw ValidationException::withMessages([
                'items' => 'El pedido no tiene productos. Agrégalos con agregar_al_pedido antes de confirmar.',
            ]);
        }

        $branch = $this->resolveBranch($branchId, $fulfillment, $withProduct);

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Indica la sucursal (branch_id) donde el cliente retira el pedido.',
            ]);
        }

        if (filled($paymentMethod)) {
            $conversation->forceFill(['payment_method_note' => mb_substr(trim((string) $paymentMethod), 0, 255)])->save();
        }

        $hadHeldSale = $conversation->pos_held_sale_id !== null;

        $heldSale = DB::transaction(fn (): PosHeldSale => $this->converter->upsertHeldSale(
            $conversation,
            $branch,
            (int) $clientId,
            $withProduct,
            $fulfillment,
            null,
            $toSource,
        ));

        AuditLogger::record(
            'whatsapp_order_confirmed',
            'WhatsApp · Pedido confirmado por el cliente: venta en pausa en '.$branch->name,
            PosHeldSale::class,
            $heldSale->getKey(),
            $heldSale->label,
            ['module' => 'whatsapp_agent', 'conversation_id' => $conversation->getKey(), 'branch_id' => $branch->getKey(), 'fulfillment' => $fulfillment->value],
        );

        if ($heldSale->wasRecentlyCreated) {
            $this->converter->notifyBranchCashiers($heldSale, $branch);
        }

        $this->escalator->escalate(
            $conversation->refresh(),
            'Pedido confirmado: en ventas en pausa de '.$branch->name.' ('.mb_strtolower($fulfillment->label()).')'
                .(filled($conversation->payment_method_note) ? ' · pago: '.$conversation->payment_method_note : '')
                .($toSource !== [] ? ' · '.count($toSource).' producto(s) por conseguir' : '')
                .'. Verificar el pago y facturar.',
            $summary,
        );

        return [
            'held_sale_id' => (int) $heldSale->getKey(),
            'branch' => (string) $branch->name,
            'items_in_register' => $withProduct->count(),
            'to_source' => array_values($toSource),
            'updated' => $hadHeldSale && ! $heldSale->wasRecentlyCreated,
        ];
    }

    /**
     * Para delivery sin sucursal indicada, despacha la que tenga más productos del pedido.
     *
     * @param  Collection<int, WhatsappConversationItem>  $items
     */
    private function resolveBranch(?int $branchId, WhatsappFulfillmentType $fulfillment, $items): ?Branch
    {
        if ($branchId !== null && $branchId > 0) {
            return Branch::query()->where('is_active', true)->find($branchId);
        }

        if ($fulfillment !== WhatsappFulfillmentType::Delivery) {
            return null;
        }

        $productIds = $items->pluck('product_id')->filter()->all();

        $bestBranchId = $productIds === [] ? null : Inventory::query()
            ->whereIn('product_id', $productIds)
            ->whereRaw('(quantity - reserved_quantity) > 0')
            ->whereIn('branch_id', Branch::query()->where('is_active', true)->select('id'))
            ->selectRaw('branch_id, COUNT(DISTINCT product_id) as products')
            ->groupBy('branch_id')
            ->orderByDesc('products')
            ->value('branch_id');

        return Branch::query()->where('is_active', true)->find($bestBranchId)
            ?? Branch::query()->where('is_active', true)->orderByDesc('is_headquarters')->orderBy('id')->first();
    }
}
