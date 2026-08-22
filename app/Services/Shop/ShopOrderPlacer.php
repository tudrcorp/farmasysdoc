<?php

namespace App\Services\Shop;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Orders\OrderTotalsCalculator;
use App\Support\Shop\ShopCart;
use App\Support\Shop\ShopCheckoutData;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Convierte el carrito de la PWA en un pedido real (`orders` + `order_items`).
 *
 * Los montos se calculan con {@see OrderTotalsCalculator}, el mismo que usa el panel
 * interno, para que un pedido web y uno cargado por un operador queden idénticos.
 */
final class ShopOrderPlacer
{
    public const CREATED_BY = 'pwa:tienda';

    public function __construct(private readonly ShopCart $cart) {}

    /**
     * @throws RuntimeException Cuando el carrito quedó vacío al confirmar.
     */
    public function place(ShopCheckoutData $checkout): Order
    {
        $itemStates = $this->cart->itemStates();

        if ($itemStates === []) {
            throw new RuntimeException('El carrito está vacío.');
        }

        return DB::transaction(function () use ($itemStates, $checkout): Order {
            $client = $this->resolveClient($checkout);
            $branch = $this->resolveBranch($checkout);
            $totals = OrderTotalsCalculator::aggregateFromItemStates($itemStates);

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'client_id' => $client->id,
                'branch_id' => $branch?->id,
                'status' => OrderStatus::Pending,
                'convenio_type' => ConvenioType::Particular,
                'delivery_recipient_name' => $checkout->name,
                'delivery_recipient_document' => $checkout->documentType.'-'.$checkout->documentNumber,
                'delivery_phone' => $checkout->phone,
                'delivery_address' => $checkout->isPickup()
                    ? ($branch?->address ?? 'Retiro en sucursal')
                    : $checkout->address,
                'delivery_city' => $checkout->isPickup() ? $branch?->city : $checkout->city,
                'delivery_state' => $checkout->isPickup() ? $branch?->state : $checkout->state,
                'delivery_notes' => $checkout->deliveryNotes,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $totals['discount_total'],
                'total' => $totals['total'],
                'notes' => $this->buildNotes($checkout, $branch),
                'created_by' => self::CREATED_BY,
                'updated_by' => self::CREATED_BY,
            ]);

            $this->createItems($order, $itemStates);

            return $order->load('items');
        });
    }

    /**
     * @param  list<array{product_id: int, quantity: float}>  $itemStates
     */
    private function createItems(Order $order, array $itemStates): void
    {
        foreach ($itemStates as $state) {
            $product = Product::query()->find($state['product_id']);

            if (! $product instanceof Product) {
                continue;
            }

            $amounts = OrderTotalsCalculator::lineAmounts($product, $state['quantity']);

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
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
    }

    /**
     * Reutiliza el cliente por correo (columna única) y completa datos faltantes.
     */
    private function resolveClient(ShopCheckoutData $checkout): Client
    {
        $client = Client::query()
            ->where('email', $checkout->email)
            ->first();

        $attributes = [
            'name' => $checkout->name,
            'document_type' => $checkout->documentType,
            'document_number' => $checkout->documentNumber,
            'phone' => $checkout->phone,
            'address' => $checkout->address !== '' ? $checkout->address : 'No indicada',
            'city' => $checkout->city !== '' ? $checkout->city : 'No indicada',
            'state' => $checkout->state !== '' ? $checkout->state : 'No indicado',
        ];

        if ($client instanceof Client) {
            $client->update([...$attributes, 'updated_by' => self::CREATED_BY]);

            return $client;
        }

        return Client::query()->create([
            ...$attributes,
            'email' => $checkout->email,
            'country' => 'Venezuela',
            'status' => 'active',
            'created_by' => self::CREATED_BY,
            'updated_by' => self::CREATED_BY,
        ]);
    }

    private function resolveBranch(ShopCheckoutData $checkout): ?Branch
    {
        if ($checkout->branchId !== null) {
            $branch = Branch::query()
                ->where('is_active', true)
                ->find($checkout->branchId);

            if ($branch instanceof Branch) {
                return $branch;
            }
        }

        $fallback = Branch::query()
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->orderBy('id')
            ->first();

        return $fallback instanceof Branch ? $fallback : null;
    }

    private function buildNotes(ShopCheckoutData $checkout, ?Branch $branch): string
    {
        $mode = $checkout->isPickup()
            ? 'Retiro en sucursal'.($branch?->name !== null ? ' · '.$branch->name : '')
            : 'Entrega a domicilio';

        $lines = [
            'Pedido web (app Farmadoc) · '.$mode,
            'Pago: '.$checkout->paymentMethodLabel(),
        ];

        if ($checkout->notes !== null && $checkout->notes !== '') {
            $lines[] = 'Nota del cliente: '.$checkout->notes;
        }

        return implode("\n", $lines);
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'WEB-'.now()->format('ymd').'-'.random_int(1000, 9999);
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
