<?php

namespace App\Livewire\Shop\Auth;

use App\Enums\ShopIdentityMethod;
use App\Services\Shop\ShopCustomerAuthenticator;
use App\Services\Shop\ShopCustomerRegistrar;
use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['hideTabBar' => true])]
#[Title('Crear cuenta')]
class Register extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $method = ShopIdentityMethod::Document->value;

    public string $documentType = 'V';

    public string $documentNumber = '';

    public string $phone = '';

    public function selectMethod(string $method): void
    {
        $this->method = ShopIdentityMethod::from($method)->value;
    }

    public function mount(): void
    {
        $draft = ShopCustomerIdentity::draft();

        $this->firstName = $draft['first_name'];
        $this->lastName = $draft['last_name'];
        $this->method = $draft['method'];
        $this->documentType = $draft['document_type'];
        $this->documentNumber = $draft['document_number'];
        $this->phone = ShopCustomerIdentity::displayPhone($draft['phone']);
    }

    public function continue(ShopCustomerRegistrar $registrar, ShopCustomerAuthenticator $authenticator): void
    {
        $authenticator->ensureNotRateLimited();

        $this->documentNumber = ShopCustomerIdentity::normalizeDocumentNumber($this->documentNumber);

        $this->validate($this->rules(), $this->messages());

        $method = ShopIdentityMethod::from($this->method);
        $documentNumber = ShopCustomerIdentity::normalizeDocumentNumber($this->documentNumber);
        $phone = $method === ShopIdentityMethod::Phone
            ? ShopCustomerIdentity::assertValidPhone(ShopCustomerIdentity::normalizePhone($this->phone))
            : '';

        $draft = [
            'first_name' => trim($this->firstName),
            'last_name' => trim($this->lastName),
            'method' => $method->value,
            'document_type' => $this->documentType,
            'document_number' => $documentNumber,
            'phone' => $phone,
        ];

        $registrar->assertIdentityIsFree($method, $draft);
        ShopCustomerIdentity::putDraft($draft);

        $this->redirectRoute('shop.register.password', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.shop.auth.register');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $method = ShopIdentityMethod::tryFrom($this->method) ?? ShopIdentityMethod::Document;

        return [
            'firstName' => ['required', 'string', 'min:2', 'max:80'],
            'lastName' => ['required', 'string', 'min:2', 'max:80'],
            'method' => ['required', Rule::enum(ShopIdentityMethod::class)],
            'documentType' => $method === ShopIdentityMethod::Document
                ? ['required', Rule::in(['V', 'E'])]
                : ['nullable'],
            'documentNumber' => $method === ShopIdentityMethod::Document
                ? ['required', 'string', 'regex:/^\d{5,10}$/']
                : ['nullable'],
            'phone' => $method === ShopIdentityMethod::Phone
                ? ['required', 'string', 'min:10', 'max:20']
                : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'firstName.required' => 'Escribe tu nombre.',
            'firstName.min' => 'El nombre es muy corto.',
            'lastName.required' => 'Escribe tu apellido.',
            'lastName.min' => 'El apellido es muy corto.',
            'documentNumber.required' => 'Escribe tu cédula.',
            'documentNumber.regex' => 'La cédula debe tener entre 5 y 10 números.',
            'phone.required' => 'Escribe tu celular.',
        ];
    }
}
