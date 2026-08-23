<?php

namespace App\Livewire\Shop\Auth;

use App\Enums\ShopIdentityMethod;
use App\Services\Shop\ShopCustomerAuthenticator;
use App\Services\Shop\ShopGoogleAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['hideTabBar' => true])]
#[Title('Entrar')]
class Login extends Component
{
    public string $method = ShopIdentityMethod::Document->value;

    public string $identifier = '';

    public string $password = '';

    public function selectMethod(string $method): void
    {
        $this->method = ShopIdentityMethod::from($method)->value;
    }

    public function authenticate(ShopCustomerAuthenticator $authenticator): void
    {
        $this->validate([
            'method' => ['required', Rule::enum(ShopIdentityMethod::class)],
            'identifier' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => 'Escribe tu cédula o tu celular.',
            'password.required' => 'Escribe tu clave.',
        ]);

        $authenticator->attempt(
            ShopIdentityMethod::from($this->method),
            $this->identifier,
            $this->password,
        );

        $this->redirectIntended(route('shop.home'), navigate: true);
    }

    public function render(ShopGoogleAuth $google): View
    {
        return view('livewire.shop.auth.login', [
            'googleEnabled' => $google->isConfigured(),
            'authError' => session('shop.auth_error'),
        ]);
    }
}
