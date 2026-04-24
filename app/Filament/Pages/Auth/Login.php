<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    /**
     * Sin tope por IP: muchos usuarios detrás de la misma NAT o alto tráfico legítimo.
     */
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}

    protected function isMultiFactorChallengeRateLimited(Authenticatable $user): bool
    {
        return false;
    }

    public function getSloganAfterLogo(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return null;
        }

        return new HtmlString(
            '<span class="fi-login-slogan">Nuestra gente, su bienestar.</span>'
        );
    }
}
