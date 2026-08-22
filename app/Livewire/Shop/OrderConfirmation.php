<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['tab' => 'cart', 'hideTabBar' => true])]
#[Title('Pedido confirmado')]
class OrderConfirmation extends Component
{
    public string $orderNumber;

    public function mount(string $order): void
    {
        $this->orderNumber = $order;

        abort_unless($this->isOwnOrder(), 404);
    }

    /**
     * Solo el navegador que creó el pedido puede verlo: el número queda en su sesión.
     */
    private function isOwnOrder(): bool
    {
        return in_array($this->orderNumber, (array) session('shop.orders', []), true);
    }

    public function order(): Order
    {
        $order = Order::query()
            ->with(['items', 'branch:id,name,address,city,phone'])
            ->where('order_number', $this->orderNumber)
            ->first();

        abort_unless($order instanceof Order, 404);

        return $order;
    }

    public function render(): View
    {
        return view('livewire.shop.order-confirmation', [
            'order' => $this->order(),
        ]);
    }
}
