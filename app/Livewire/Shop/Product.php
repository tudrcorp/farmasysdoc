<?php

namespace App\Livewire\Shop;

use App\Livewire\Shop\Concerns\InteractsWithShopCart;
use App\Models\Product as ProductModel;
use App\Support\Orders\OrderTotalsCalculator;
use App\Support\Shop\ShopCatalog;
use App\Support\Storefront\StorefrontProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ficha de producto: ocupa la pantalla completa, sin barra inferior ni desplazamiento.
 */
#[Layout('layouts.shop', ['tab' => 'search', 'hideTabBar' => true])]
class Product extends Component
{
    use InteractsWithShopCart;

    public int $productId;

    public float $quantity = 1;

    private ?ProductModel $resolved = null;

    public function mount(int $product): void
    {
        $this->productId = $product;

        abort_unless($this->model() instanceof ProductModel, 404);
    }

    public function model(): ProductModel
    {
        if ($this->resolved instanceof ProductModel) {
            return $this->resolved;
        }

        $product = ShopCatalog::findPresentable($this->productId);

        abort_unless($product instanceof ProductModel, 404);

        $this->resolved = $product;

        return $product;
    }

    public function increaseQuantity(): void
    {
        $max = $this->maxQuantity();

        $this->quantity = min($max, $this->quantity + 1);
    }

    public function decreaseQuantity(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function addSelectionToCart(): void
    {
        $this->addToCart($this->productId, $this->quantity);
        $this->quantity = 1;
    }

    private function maxQuantity(): float
    {
        $available = floor((float) ($this->model()->getAttribute('stock_available') ?? 0));

        return max(1.0, min($available, 99.0));
    }

    /**
     * La ficha solo tiene dos líneas para la descripción: se recorta en limpio.
     */
    private function shortDescription(ProductModel $product): ?string
    {
        $description = trim((string) ($product->description ?? ''));

        return $description === '' ? null : Str::limit($description, 150);
    }

    public function render(): View
    {
        $product = $this->model();
        $amounts = OrderTotalsCalculator::lineAmounts($product, $this->quantity);
        $maxQuantity = $this->maxQuantity();

        return view('livewire.shop.product', [
            'product' => StorefrontProductPresenter::fromProduct($product),
            'description' => $this->shortDescription($product),
            'concentration' => filled($product->concentration) ? (string) $product->concentration : null,
            'netContent' => filled($product->net_content_label) ? (string) $product->net_content_label : null,
            'lineTotal' => $amounts['line_total'],
            'taxAmount' => $amounts['tax_amount'],
            'maxQuantity' => $maxQuantity,
            'inCartQuantity' => $this->cart()->quantityFor($this->productId),
        ]);
    }
}
