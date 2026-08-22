<?php

namespace App\Livewire\Shop;

use App\Livewire\Shop\Concerns\InteractsWithShopCart;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['tab' => 'cart'])]
#[Title('Carrito')]
class Cart extends Component
{
    use InteractsWithShopCart;

    public bool $confirmingClear = false;

    public function askClear(): void
    {
        $this->confirmingClear = true;
    }

    public function cancelClear(): void
    {
        $this->confirmingClear = false;
    }

    public function confirmClear(): void
    {
        $this->confirmingClear = false;
        $this->clearCart();
    }

    public function goToCheckout(): void
    {
        if ($this->cart()->isEmpty()) {
            $this->dispatch('shop-toast', message: 'Tu carrito está vacío');

            return;
        }

        $this->redirectRoute('shop.checkout', navigate: true);
    }

    public function render(): View
    {
        $cart = $this->cart();
        $lines = $cart->lines();

        $requiresPrescription = false;

        foreach ($lines as $line) {
            if (($line['product']['requires_prescription'] ?? false) === true) {
                $requiresPrescription = true;

                break;
            }
        }

        return view('livewire.shop.cart', [
            'lines' => $lines,
            'totals' => $cart->totals($lines),
            'requiresPrescription' => $requiresPrescription,
        ]);
    }
}
