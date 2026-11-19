<?php

namespace App\Services\Orders;

use App\Enums\ConvenioType;
use App\Enums\OrderFulfillmentType;
use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerCompany;
use App\Models\Product;
use App\Services\Partners\PartnerPanelActivityRecorder;
use App\Support\Inventory\LasDeliciasBranch;
use App\Support\Orders\OrderTotalsCalculator;
use App\Support\Orders\PartnerMedicineCharge;
use App\Support\Orders\PartnerOrderDeliverySync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pedido pendiente creado desde la pantalla pública del aliado (sin sesión de usuario).
 */
final class PartnerGuestOrderPlacer
{
    public const MAX_LINES = 40;

    /**
     * @param  array{
     *     lines: list<array{product_id?: mixed, quantity?: mixed}>,
     *     is_wholesale?: bool,
     *     fulfillment?: string,
     *     recipient_name?: ?string,
     *     recipient_phone?: ?string,
     *     recipient_document?: ?string,
     *     address?: ?string,
     *     city?: ?string,
     *     state?: ?string,
     *     delivery_notes?: ?string,
     * }  $data
     */
    public function place(PartnerCompany $company, array $data): Order
    {
        if (! $company->is_active || ! $company->can_place_orders) {
            throw ValidationException::withMessages([
                'partnerCode' => 'Este aliado no puede hacer pedidos en este momento.',
            ]);
        }

        $branch = LasDeliciasBranch::resolve();

        if ($branch === null) {
            throw ValidationException::withMessages([
                'lines' => 'No hay una sucursal activa de Las Delicias.',
            ]);
        }

        $fulfillment = OrderFulfillmentType::tryFrom((string) ($data['fulfillment'] ?? ''));

        if ($fulfillment === null) {
            throw ValidationException::withMessages([
                'fulfillment' => 'Indique el tipo de entrega.',
            ]);
        }

        $lines = $this->normalizeLines($data['lines'] ?? []);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Agregue al menos un producto.',
            ]);
        }

        if (count($lines) > self::MAX_LINES) {
            throw ValidationException::withMessages([
                'lines' => 'Puede incluir hasta '.self::MAX_LINES.' productos por pedido.',
            ]);
        }

        $actor = 'aliado:'.(string) $company->code;

        return DB::transaction(function () use ($company, $branch, $data, $lines, $fulfillment, $actor): Order {
            $profit = $company->medicineDispatchProfitPercent();
            $inventories = [];

            foreach ($lines as $line) {
                $inventory = Inventory::query()
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $line['product_id'])
                    ->whereHas('product', fn ($query) => $query->where('is_active', true))
                    ->with('product')
                    ->lockForUpdate()
                    ->first();

                if (! $inventory instanceof Inventory || $inventory->product === null) {
                    throw ValidationException::withMessages([
                        'lines' => 'Algún producto ya no está en el inventario de Las Delicias.',
                    ]);
                }

                $inventories[] = ['inventory' => $inventory, 'quantity' => $line['quantity']];
            }

            $order = Order::query()->create([
                'order_number' => 'TMP-'.Str::lower((string) Str::ulid()),
                'client_id' => null,
                'branch_id' => $branch->id,
                'partner_company_id' => $company->getKey(),
                'status' => OrderStatus::Pending,
                'convenio_type' => ConvenioType::Particular,
                'is_wholesale' => (bool) ($data['is_wholesale'] ?? false),
                'partner_fulfillment_type' => $fulfillment,
                'partner_payment_terms' => null,
                'partner_cash_payment_method' => null,
                'partner_pago_movil_reference' => null,
                'partner_zelle_reference_name' => null,
                'partner_zelle_transaction_number' => null,
                'partner_cash_payment_proof_path' => null,
                'delivery_recipient_name' => $this->nullableString($data['recipient_name'] ?? null),
                'delivery_phone' => $this->nullableString($data['recipient_phone'] ?? null),
                'delivery_recipient_document' => $this->nullableString($data['recipient_document'] ?? null),
                'delivery_address' => $this->nullableString($data['address'] ?? null),
                'delivery_city' => $this->nullableString($data['city'] ?? null),
                'delivery_state' => $this->nullableString($data['state'] ?? null),
                'delivery_notes' => $this->nullableString($data['delivery_notes'] ?? null),
                'subtotal' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => 0,
                'notes' => 'Pedido desde el acceso con código de aliado.',
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            foreach ($inventories as $row) {
                /** @var Inventory $inventory */
                $inventory = $row['inventory'];
                /** @var Product $product */
                $product = $inventory->product;

                OrderItem::query()->create(array_merge(
                    OrderTotalsCalculator::lineAmounts($product, (float) $row['quantity'], $profit),
                    [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'inventory_id' => $inventory->id,
                        'quantity' => $row['quantity'],
                    ],
                ));
            }

            $order->assignCanonicalOrderNumber();
            $order->recalculateTotalsFromItems();
            PartnerMedicineCharge::forCompany($company)->rememberOn($order);
            PartnerOrderDeliverySync::sync($order->fresh());
            $order->refresh();

            PartnerPanelActivityRecorder::record(
                (int) $company->getKey(),
                'partner_guest_order_created',
                'El aliado hizo el pedido '.$order->order_number.' desde el acceso con código ('.count($lines).' productos).',
                null,
                $order,
                (string) $order->order_number,
                ['items' => count($lines), 'channel' => 'partner_guest'],
            );

            return $order;
        });
    }

    /**
     * @param  list<array{product_id?: mixed, quantity?: mixed}>  $lines
     * @return list<array{product_id: int, quantity: float}>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = $line['quantity'] ?? null;

            if ($productId <= 0 || ! is_numeric($quantity)) {
                continue;
            }

            $quantity = round((float) $quantity, 3);

            if ($quantity < 1 || $quantity > 9999) {
                throw ValidationException::withMessages([
                    'lines' => 'La cantidad de cada producto debe estar entre 1 y 9999.',
                ]);
            }

            $normalized[$productId] = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];
        }

        return array_values($normalized);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
