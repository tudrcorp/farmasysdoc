<?php

namespace App\Livewire\Shop\Auth;

use App\Services\Shop\ShopGoogleAuth;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['hideTabBar' => true])]
#[Title('Bienvenido')]
class Welcome extends Component
{
    public function render(ShopGoogleAuth $google): View
    {
        return view('livewire.shop.auth.welcome', [
            'googleEnabled' => $google->isConfigured(),
            'authError' => session('shop.auth_error'),
        ]);
    }
}
