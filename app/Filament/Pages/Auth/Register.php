<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Register extends BaseRegister
{
    public function getSloganAfterLogo(): string|Htmlable|null
    {
        return new HtmlString(
            '<span class="fi-login-slogan">Nuestra gente, su bienestar.</span>'
        );
    }
}
