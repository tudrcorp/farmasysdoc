<?php

namespace App\Livewire\Shop\Auth;

use App\Models\ShopCustomer;
use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['hideTabBar' => true])]
#[Title('Cuenta lista')]
class RegisterSuccess extends Component
{
    public function mount(): void
    {
        if (! session()->has(ShopCustomerIdentity::SESSION_JUST_REGISTERED)) {
            $this->redirectRoute('shop.home', navigate: true);
        }
    }

    public function enter(): void
    {
        session()->forget(ShopCustomerIdentity::SESSION_JUST_REGISTERED);

        $this->redirectRoute('shop.home', navigate: true);
    }

    public function render(): View
    {
        $customer = Auth::guard('shop')->user();

        return view('livewire.shop.auth.register-success', [
            'firstName' => $customer instanceof ShopCustomer ? $customer->firstName() : 'cliente',
        ]);
    }
}
