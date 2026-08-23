<?php

namespace App\Livewire\Shop\Auth;

use App\Enums\ShopIdentityMethod;
use App\Services\Shop\ShopCustomerAuthenticator;
use App\Services\Shop\ShopCustomerRegistrar;
use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['hideTabBar' => true])]
#[Title('Crea tu clave')]
class RegisterPassword extends Component
{
    public string $password = '';

    public string $passwordConfirmation = '';

    /**
     * @var array{
     *     first_name: string,
     *     last_name: string,
     *     method: string,
     *     document_type: string,
     *     document_number: string,
     *     phone: string
     * }
     */
    public array $draft = [];

    public function mount(): void
    {
        $this->draft = ShopCustomerIdentity::draft();

        $hasName = trim($this->draft['first_name']) !== '' && trim($this->draft['last_name']) !== '';
        $hasDocument = $this->draft['method'] === ShopIdentityMethod::Document->value
            && $this->draft['document_number'] !== '';
        $hasPhone = $this->draft['method'] === ShopIdentityMethod::Phone->value
            && $this->draft['phone'] !== '';

        if (! $hasName || (! $hasDocument && ! $hasPhone)) {
            $this->redirectRoute('shop.register', navigate: true);
        }
    }

    public function register(ShopCustomerRegistrar $registrar, ShopCustomerAuthenticator $authenticator): void
    {
        $authenticator->ensureNotRateLimited();

        $this->validate([
            'password' => ['required', 'string', 'min:4'],
            'passwordConfirmation' => ['required', 'same:password'],
        ], [
            'password.required' => 'Crea una clave.',
            'password.min' => 'La clave debe tener al menos 4 caracteres.',
            'passwordConfirmation.required' => 'Confirma tu clave.',
            'passwordConfirmation.same' => 'Las claves no coinciden.',
        ]);

        $method = ShopIdentityMethod::from($this->draft['method']);
        $registrar->assertIdentityIsFree($method, $this->draft);
        $registrar->registerWithPassword($this->draft, $this->password);

        $this->redirectRoute('shop.register.success', navigate: true);
    }

    public function render(): View
    {
        $method = ShopIdentityMethod::tryFrom($this->draft['method']) ?? ShopIdentityMethod::Document;

        return view('livewire.shop.auth.register-password', [
            'identityLabel' => $method === ShopIdentityMethod::Document
                ? $this->draft['document_type'].'-'.$this->draft['document_number']
                : ShopCustomerIdentity::displayPhone($this->draft['phone']),
        ]);
    }
}
