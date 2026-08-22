<?php

namespace App\Support\Shop;

use App\Models\Product;
use App\Support\Orders\OrderTotalsCalculator;
use App\Support\Storefront\StorefrontProductPresenter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * Carrito de la PWA pública, persistido en sesión.
 *
 * Solo guarda `product_id` y `quantity`: los precios se recalculan siempre desde el
 * catálogo con {@see OrderTotalsCalculator} para que el carrito nunca quede con
 * precios obsoletos entre visitas.
 */
final class ShopCart
{
    private const SESSION_KEY = 'shop.cart';

    public const MAX_QUANTITY_PER_LINE = 99;

    /**
     * Cantidades en el carrito, indexadas por id de producto.
     *
     * @return array<int, float>
     */
    public function raw(): array
    {
        $stored = Session::get(self::SESSION_KEY, []);

        if (! is_array($stored)) {
            return [];
        }

        $lines = [];

        foreach ($stored as $productId => $quantity) {
            $id = (int) $productId;
            $qty = (float) $quantity;

            if ($id > 0 && $qty > 0) {
                $lines[$id] = $qty;
            }
        }

        return $lines;
    }

    public function add(int $productId, float $quantity = 1): void
    {
        $lines = $this->raw();
        $current = $lines[$productId] ?? 0.0;

        $this->setQuantity($productId, $current + $quantity);
    }

    public function setQuantity(int $productId, float $quantity): void
    {
        $lines = $this->raw();

        if ($quantity <= 0) {
            unset($lines[$productId]);
            $this->persist($lines);

            return;
        }

        $available = $this->availableStockFor($productId);
        $capped = min($quantity, self::MAX_QUANTITY_PER_LINE);

        if ($available > 0) {
            $capped = min($capped, $available);
        }

        $lines[$productId] = max(1.0, floor($capped));

        $this->persist($lines);
    }

    public function remove(int $productId): void
    {
        $lines = $this->raw();
        unset($lines[$productId]);

        $this->persist($lines);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Sustituye el carrito por las cantidades de la vitrina pública.
     *
     * @param  array<int, float>  $quantitiesByProductId
     */
    public function replace(array $quantitiesByProductId): void
    {
        Session::forget(self::SESSION_KEY);

        foreach ($quantitiesByProductId as $productId => $quantity) {
            $this->setQuantity((int) $productId, (float) $quantity);
        }
    }

    /**
     * Unidades totales en el carrito (para el badge de la barra inferior).
     */
    public function count(): int
    {
        return (int) array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    public function has(int $productId): bool
    {
        return array_key_exists($productId, $this->raw());
    }

    public function quantityFor(int $productId): float
    {
        return (float) ($this->raw()[$productId] ?? 0);
    }

    /**
     * Líneas hidratadas con datos de catálogo y montos calculados.
     *
     * Descarta en silencio productos desactivados o eliminados del catálogo.
     *
     * @return list<array{
     *     product_id: int,
     *     quantity: float,
     *     stock_available: float,
     *     unit_price: float,
     *     list_price: float,
     *     discount_percent: float,
     *     line_subtotal: float,
     *     tax_amount: float,
     *     line_total: float,
     *     product: array<string, mixed>,
     * }>
     */
    public function lines(): array
    {
        $quantities = $this->raw();

        if ($quantities === []) {
            return [];
        }

        $products = ShopCatalog::productsForCart(array_keys($quantities));
        $lines = [];
        $survivingIds = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product instanceof Product) {
                continue;
            }

            $amounts = OrderTotalsCalculator::lineAmounts($product, $quantity);
            $survivingIds[$productId] = $quantity;

            $lines[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'stock_available' => round((float) ($product->getAttribute('stock_available') ?? 0), 3),
                'unit_price' => $amounts['unit_price'],
                'list_price' => round((float) $product->sale_price, 2),
                'discount_percent' => round((float) $product->discount_percent, 2),
                'line_subtotal' => $amounts['line_subtotal'],
                'tax_amount' => $amounts['tax_amount'],
                'line_total' => $amounts['line_total'],
                'product' => StorefrontProductPresenter::fromProduct($product),
            ];
        }

        if (count($survivingIds) !== count($quantities)) {
            $this->persist($survivingIds);
        }

        return $lines;
    }

    /**
     * Totales del carrito desglosados para la interfaz.
     *
     * @param  list<array<string, mixed>>|null  $lines  Reutiliza líneas ya hidratadas si se pasan.
     * @return array{net: float, tax: float, discount: float, total: float, units: int}
     */
    public function totals(?array $lines = null): array
    {
        $lines ??= $this->lines();

        $net = 0.0;
        $tax = 0.0;
        $discount = 0.0;
        $units = 0;

        foreach ($lines as $line) {
            $net += (float) $line['line_subtotal'];
            $tax += (float) $line['tax_amount'];
            $discount += ((float) $line['list_price'] - (float) $line['unit_price']) * (float) $line['quantity'];
            $units += (int) $line['quantity'];
        }

        return [
            'net' => round($net, 2),
            'tax' => round($tax, 2),
            'discount' => round($discount, 2),
            'total' => round($net + $tax, 2),
            'units' => $units,
        ];
    }

    /**
     * Estado del carrito listo para persistir como pedido.
     *
     * @return list<array{product_id: int, quantity: float}>
     */
    public function itemStates(): array
    {
        $states = [];

        foreach ($this->lines() as $line) {
            $states[] = [
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
            ];
        }

        return $states;
    }

    public function requiresPrescription(): bool
    {
        foreach ($this->lines() as $line) {
            if ($line['product']['requires_prescription'] === true) {
                return true;
            }
        }

        return false;
    }

    private function availableStockFor(int $productId): float
    {
        return (float) Cache::remember('shop.stock.avail.'.$productId, 20, function () use ($productId): float {
            $product = ShopCatalog::productsForCart([$productId])->get($productId);

            return $product instanceof Product
                ? max(0.0, floor((float) ($product->getAttribute('stock_available') ?? 0)))
                : 0.0;
        });
    }

    /**
     * @param  array<int, float>  $lines
     */
    private function persist(array $lines): void
    {
        if ($lines === []) {
            Session::forget(self::SESSION_KEY);

            return;
        }

        Session::put(self::SESSION_KEY, $lines);
    }
}
