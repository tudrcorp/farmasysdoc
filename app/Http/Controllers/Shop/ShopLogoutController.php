<?php

namespace App\Http\Controllers\Shop;

use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopLogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('shop')->logout();

        $request->session()->forget([
            ShopCustomerIdentity::SESSION_DRAFT,
            ShopCustomerIdentity::SESSION_JUST_REGISTERED,
        ]);

        return redirect()->route('shop.home');
    }
}
