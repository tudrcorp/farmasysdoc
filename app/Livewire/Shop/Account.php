<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['tab' => 'account'])]
#[Title('Mi cuenta')]
class Account extends Component
{
    /**
     * Pedidos hechos desde este dispositivo (la app pública no exige cuenta).
     *
     * @return Collection<int, Order>
     */
    public function orders(): Collection
    {
        $numbers = array_values(array_unique((array) session('shop.orders', [])));

        if ($numbers === []) {
            return collect();
        }

        return Order::query()
            ->with('items:id,order_id,product_name_snapshot,quantity')
            ->whereIn('order_number', $numbers)
            ->orderByDesc('created_at')
            ->get();
    }

    #[On('shop-profile-updated')]
    public function refreshProfile(): void {}

    public function render(): View
    {
        $orders = $this->orders();

        return view('livewire.shop.account', [
            'orders' => $orders,
            'totalSpent' => round((float) $orders->sum('total'), 2),
        ]);
    }
}
