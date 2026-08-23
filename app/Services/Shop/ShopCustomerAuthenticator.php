<?php

namespace App\Services\Shop;

use App\Enums\ShopIdentityMethod;
use App\Models\ShopCustomer;
use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class ShopCustomerAuthenticator
{
    public function attempt(ShopIdentityMethod $method, string $identifier, string $password): ShopCustomer
    {
        $this->ensureNotRateLimited();

        $customer = $this->findByIdentity($method, $identifier);

        if (! $customer instanceof ShopCustomer) {
            $this->hitRateLimit();

            throw ValidationException::withMessages([
                'identifier' => $method === ShopIdentityMethod::Document
                    ? 'No encontramos una cuenta con esa cédula.'
                    : 'No encontramos una cuenta con ese teléfono.',
            ]);
        }

        if (! $customer->hasPassword()) {
            $this->hitRateLimit();

            throw ValidationException::withMessages([
                'identifier' => 'Esta cuenta entra con Google. Usa el botón de Google.',
            ]);
        }

        if (! Hash::check($password, (string) $customer->password)) {
            $this->hitRateLimit();

            throw ValidationException::withMessages([
                'password' => 'La clave no es correcta.',
            ]);
        }

        $this->clearRateLimit();
        Auth::guard('shop')->login($customer, remember: true);
        ShopCustomerIdentity::forgetDraft();

        return $customer;
    }

    public function findByIdentity(ShopIdentityMethod $method, string $identifier): ?ShopCustomer
    {
        if ($method === ShopIdentityMethod::Document) {
            $number = ShopCustomerIdentity::normalizeDocumentNumber($identifier);

            if ($number === '') {
                return null;
            }

            return ShopCustomer::query()
                ->where('document_number', $number)
                ->first();
        }

        $phone = ShopCustomerIdentity::normalizePhone($identifier);

        if ($phone === null) {
            return null;
        }

        return ShopCustomer::query()
            ->where('phone', $phone)
            ->first();
    }

    public function ensureNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->rateLimitKey(), 8)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->rateLimitKey());

        throw ValidationException::withMessages([
            'identifier' => 'Demasiados intentos. Espera '.$seconds.' segundos e inténtalo de nuevo.',
        ]);
    }

    public function hitRateLimit(): void
    {
        RateLimiter::hit($this->rateLimitKey(), 60);
    }

    public function clearRateLimit(): void
    {
        RateLimiter::clear($this->rateLimitKey());
    }

    private function rateLimitKey(): string
    {
        return 'shop-customer-auth:'.(string) request()->ip();
    }
}
