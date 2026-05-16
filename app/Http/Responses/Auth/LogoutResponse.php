<?php

namespace App\Http\Responses\Auth;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as FilamentLogoutResponseContract;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as FortifyLogoutResponseContract;

class LogoutResponse implements FilamentLogoutResponseContract, FortifyLogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('home');
    }
}
