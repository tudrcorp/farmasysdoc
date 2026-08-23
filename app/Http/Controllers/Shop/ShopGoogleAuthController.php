<?php

namespace App\Http\Controllers\Shop;

use App\Services\Shop\ShopGoogleAuth;
use Illuminate\Http\RedirectResponse;
use Throwable;

class ShopGoogleAuthController
{
    public function redirect(ShopGoogleAuth $google): RedirectResponse
    {
        if (! $google->isConfigured()) {
            return redirect()
                ->route('shop.welcome')
                ->with('shop.auth_error', 'El ingreso con Google todavía no está configurado.');
        }

        return $google->redirect();
    }

    public function callback(ShopGoogleAuth $google): RedirectResponse
    {
        if (! $google->isConfigured()) {
            return redirect()
                ->route('shop.welcome')
                ->with('shop.auth_error', 'El ingreso con Google todavía no está configurado.');
        }

        try {
            $result = $google->callback();
        } catch (Throwable) {
            return redirect()
                ->route('shop.welcome')
                ->with('shop.auth_error', 'No pudimos conectar con Google. Inténtalo de nuevo.');
        }

        return redirect()->route($result['created'] ? 'shop.register.success' : 'shop.home');
    }
}
