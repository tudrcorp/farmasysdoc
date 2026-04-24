<?php

namespace App\Filament\Pages\Auth\PasswordReset;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}
}
