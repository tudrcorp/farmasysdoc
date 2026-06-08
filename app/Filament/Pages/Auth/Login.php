<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\Cash\CashierShiftLock;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

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

    public function mount(): void
    {
        parent::mount();

        $blockedMessage = session()->pull('cashier_shift_locked_message');
        if (is_string($blockedMessage) && $blockedMessage !== '') {
            $this->addError('data.email', $blockedMessage);
        }

        $closeSuccessMessage = session()->pull('cashier_physical_close_success');
        if (is_string($closeSuccessMessage) && $closeSuccessMessage !== '') {
            Notification::make()
                ->title('Turno cerrado')
                ->body($closeSuccessMessage)
                ->success()
                ->send();
        }
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $email = (string) ($data['email'] ?? '');

        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();
            if ($user instanceof User && CashierShiftLock::isLocked($user)) {
                throw ValidationException::withMessages([
                    'data.email' => CashierShiftLock::loginBlockedMessage($user),
                ]);
            }
        }

        return parent::authenticate();
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
