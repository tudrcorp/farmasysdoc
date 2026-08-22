<?php

namespace App\Livewire\Shop;

use App\Livewire\Shop\Concerns\InteractsWithShopCart;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Shop\ShopCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.shop', ['tab' => 'home'])]
#[Title('Inicio')]
class Home extends Component
{
    use InteractsWithShopCart;

    public function greeting(): string
    {
        $hour = now()->hour;

        if ($hour < 12) {
            return 'Buenos días';
        }

        if ($hour < 19) {
            return 'Buenas tardes';
        }

        return 'Buenas noches';
    }

    public function usdVesRate(): ?float
    {
        try {
            return app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now());
        } catch (Throwable) {
            return null;
        }
    }

    public function render(): View
    {
        $catalog = ShopCatalog::home(10);

        return view('livewire.shop.home', [
            'greeting' => $this->greeting(),
            'categories' => $catalog['categories'],
            'bestsellers' => $catalog['bestsellers'],
            'offers' => $catalog['offers'],
            'hasOffers' => $catalog['hasOffers'],
            'usdVesRate' => $this->usdVesRate(),
            'cartQuantities' => $this->cart()->raw(),
        ]);
    }

    protected function shouldRefreshAfterCartChange(): bool
    {
        return false;
    }
}
