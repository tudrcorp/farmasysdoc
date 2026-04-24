<?php

namespace App\Filament\Pages\Auth\PasswordReset;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;

class ResetPassword extends BaseResetPassword
{
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}

    protected function isResetPasswordRateLimited(?string $email): bool
    {
        return false;
    }
}
