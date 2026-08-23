<?php

namespace App\Services\Shop;

use App\Models\ShopCustomer;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class ShopGoogleAuth
{
    public function __construct(private ShopCustomerRegistrar $registrar) {}

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl($this->redirectUrl())
            ->redirect();
    }

    /**
     * @return array{customer: ShopCustomer, created: bool}
     */
    public function callback(): array
    {
        $googleUser = Socialite::driver('google')
            ->redirectUrl($this->redirectUrl())
            ->user();

        return $this->registrar->registerOrLoginWithGoogle($this->payload($googleUser));
    }

    public function redirectUrl(): string
    {
        $configured = (string) config('services.google.redirect');

        return $configured !== '' ? $configured : route('shop.google.callback');
    }

    /**
     * @return array{id: string, email: ?string, first_name: string, last_name: ?string, avatar: ?string}
     */
    private function payload(SocialiteUser $user): array
    {
        $raw = [];

        try {
            $raw = is_array($user->user ?? null) ? $user->user : [];
        } catch (Throwable) {
            $raw = [];
        }

        $fullName = trim((string) $user->getName());
        $firstName = trim((string) ($raw['given_name'] ?? ''));
        $lastName = trim((string) ($raw['family_name'] ?? ''));

        if ($firstName === '' && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $firstName = (string) array_shift($parts);
            $lastName = implode(' ', $parts);
        }

        if ($firstName === '') {
            $firstName = 'Cliente';
        }

        $email = $user->getEmail();

        return [
            'id' => (string) $user->getId(),
            'email' => filled($email) ? mb_strtolower(trim((string) $email)) : null,
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : null,
            'avatar' => $user->getAvatar(),
        ];
    }
}
