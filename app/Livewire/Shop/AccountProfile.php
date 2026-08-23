<?php

namespace App\Livewire\Shop;

use App\Models\ShopCustomer;
use App\Services\Shop\ShopCustomerProfileUpdater;
use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AccountProfile extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $documentType = 'V';

    public string $documentNumber = '';

    public string $phone = '';

    public function mount(): void
    {
        $customer = $this->customer();

        $this->firstName = (string) $customer->first_name;
        $this->lastName = (string) $customer->last_name;
        $this->documentType = in_array($customer->document_type, ['V', 'E'], true)
            ? (string) $customer->document_type
            : 'V';
        $this->documentNumber = (string) $customer->document_number;
        $this->phone = ShopCustomerIdentity::displayPhone($customer->phone);
    }

    public function save(ShopCustomerProfileUpdater $updater): void
    {
        $this->documentNumber = ShopCustomerIdentity::normalizeDocumentNumber($this->documentNumber);

        $this->validate([
            'firstName' => ['required', 'string', 'min:2', 'max:80'],
            'lastName' => ['required', 'string', 'min:2', 'max:80'],
            'documentType' => ['required', Rule::in(['V', 'E'])],
            'documentNumber' => ['required', 'string', 'regex:/^\d{5,10}$/'],
            'phone' => ['required', 'string', 'min:10', 'max:20'],
        ], [
            'firstName.required' => 'Escribe tu nombre.',
            'firstName.min' => 'El nombre es muy corto.',
            'lastName.required' => 'Escribe tu apellido.',
            'lastName.min' => 'El apellido es muy corto.',
            'documentNumber.required' => 'Escribe tu cédula.',
            'documentNumber.regex' => 'La cédula debe tener entre 5 y 10 números.',
            'phone.required' => 'Escribe tu teléfono.',
        ]);

        $customer = $updater->update($this->customer(), [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'document_type' => $this->documentType,
            'document_number' => $this->documentNumber,
            'phone' => $this->phone,
        ]);

        $this->phone = ShopCustomerIdentity::displayPhone($customer->phone);
        $this->dispatch('shop-profile-updated');
        $this->dispatch('shop-toast', message: 'Tus datos quedaron guardados');
    }

    private function customer(): ShopCustomer
    {
        $customer = ShopCustomer::current();

        abort_unless($customer instanceof ShopCustomer, 403);

        return $customer;
    }

    public function render(): View
    {
        return view('livewire.shop.account-profile');
    }
}
