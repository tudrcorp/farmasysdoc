<?php

namespace App\Livewire\Shop\Concerns;

use App\Support\Shop\ShopCart;

/**
 * Acciones de carrito compartidas por las pantallas de la tienda.
 *
 * Cada mutación emite `cart-updated` para que la barra inferior y el badge se
 * refresquen sin recargar la pantalla completa.
 */
trait InteractsWithShopCart
{
    public function cart(): ShopCart
    {
        return app(ShopCart::class);
    }

    public function addToCart(int $productId, float $quantity = 1): void
    {
        $this->cart()->add($productId, $quantity);

        $this->afterCartChanged('Agregado al carrito');
    }

    public function increment(int $productId): void
    {
        $cart = $this->cart();
        $cart->setQuantity($productId, $cart->quantityFor($productId) + 1);

        $this->afterCartChanged();
    }

    public function decrement(int $productId): void
    {
        $cart = $this->cart();
        $next = $cart->quantityFor($productId) - 1;

        if ($next <= 0) {
            $cart->remove($productId);
        } else {
            $cart->setQuantity($productId, $next);
        }

        $this->afterCartChanged();
    }

    public function removeFromCart(int $productId): void
    {
        $this->cart()->remove($productId);

        $this->afterCartChanged('Producto eliminado');
    }

    public function clearCart(): void
    {
        $this->cart()->clear();

        $this->afterCartChanged('Carrito vacío');
    }

    protected function afterCartChanged(?string $message = null): void
    {
        $this->dispatch('cart-updated', count: $this->cart()->count());

        if ($message !== null) {
            $this->dispatch('shop-toast', message: $message);
        }

        if (! $this->shouldRefreshAfterCartChange()) {
            $this->skipRender();
        }
    }

    /**
     * Las grillas de catálogo actualizan el stepper en el cliente; no reconsultan el buscador.
     */
    protected function shouldRefreshAfterCartChange(): bool
    {
        return true;
    }
}
