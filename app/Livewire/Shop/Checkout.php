<?php

namespace App\Livewire\Shop;

use App\Livewire\Shop\Concerns\InteractsWithShopCart;
use App\Models\Branch;
use App\Models\ShopAddress;
use App\Models\ShopCustomer;
use App\Services\Shop\ShopAddressBook;
use App\Services\Shop\ShopOrderPlacer;
use App\Support\Shop\ShopCheckoutData;
use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.shop', ['tab' => 'cart', 'hideTabBar' => true])]
#[Title('Checkout')]
class Checkout extends Component
{
    use InteractsWithShopCart;

    public const STEP_FULFILLMENT = 1;

    public const STEP_CONTACT = 2;

    public const STEP_PAYMENT = 3;

    public int $step = self::STEP_FULFILLMENT;

    public string $fulfillment = ShopCheckoutData::FULFILLMENT_DELIVERY;

    public ?int $branchId = null;

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $deliveryNotes = '';

    public string $addressLabel = '';

    public ?int $addressId = null;

    public bool $composingAddress = false;

    public string $name = '';

    public string $documentType = 'CC';

    public string $documentNumber = '';

    public string $phone = '';

    public string $email = '';

    public string $paymentMethod = 'pago_movil';

    public string $notes = '';

    public bool $placing = false;

    public function mount(): void
    {
        if ($this->cart()->isEmpty()) {
            $this->redirectRoute('shop.cart', navigate: true);

            return;
        }

        $this->branchId ??= $this->branches()->first()?->id;

        foreach ((array) session('shop.checkout.last', []) as $key => $value) {
            if (property_exists($this, $key) && is_string($value)) {
                $this->{$key} = $value;
            }
        }

        $this->prefillFromCustomer();
    }

    private function prefillFromCustomer(): void
    {
        $customer = ShopCustomer::current();

        if (! $customer instanceof ShopCustomer) {
            return;
        }

        if ($this->name === '') {
            $this->name = $customer->fullName();
        }

        if ($this->documentNumber === '' && filled($customer->document_number)) {
            $this->documentType = $customer->checkoutDocumentType();
            $this->documentNumber = (string) $customer->document_number;
        }

        if ($this->phone === '' && filled($customer->phone)) {
            $this->phone = ShopCustomerIdentity::displayPhone((string) $customer->phone);
        }

        if ($this->email === '' && filled($customer->email)) {
            $this->email = (string) $customer->email;
        }

        $this->prefillDeliveryAddress();
    }

    private function prefillDeliveryAddress(): void
    {
        $addresses = $this->savedAddresses();

        if ($addresses->isEmpty()) {
            $this->composingAddress = true;
            $this->addressId = null;

            return;
        }

        $lastId = session('shop.checkout.last.addressId');
        $selected = $addresses->firstWhere('id', $lastId)
            ?? $addresses->firstWhere('is_primary', true)
            ?? $addresses->first();

        if ($selected instanceof ShopAddress) {
            $this->applySavedAddress($selected);
        }
    }

    /**
     * @return Collection<int, ShopAddress>
     */
    public function savedAddresses(): Collection
    {
        $customer = ShopCustomer::current();

        if (! $customer instanceof ShopCustomer) {
            return collect();
        }

        return $customer->addresses()->get();
    }

    public function selectAddress(int $id): void
    {
        $this->applySavedAddress(app(ShopAddressBook::class)->findOwned($this->customer(), $id));
        $this->resetValidation();
    }

    public function startNewAddress(): void
    {
        $this->composingAddress = true;
        $this->addressId = null;
        $this->address = '';
        $this->city = '';
        $this->state = '';
        $this->deliveryNotes = '';
        $this->addressLabel = '';
        $this->resetValidation();
    }

    public function cancelNewAddress(): void
    {
        $primary = $this->savedAddresses()->firstWhere('is_primary', true)
            ?? $this->savedAddresses()->first();

        if ($primary instanceof ShopAddress) {
            $this->applySavedAddress($primary);
        } else {
            $this->composingAddress = true;
        }

        $this->resetValidation();
    }

    private function applySavedAddress(ShopAddress $address): void
    {
        $this->composingAddress = false;
        $this->addressId = $address->id;
        $this->address = (string) $address->address_line;
        $this->city = (string) $address->city;
        $this->state = (string) $address->state;
        $this->deliveryNotes = (string) $address->reference;
        $this->addressLabel = (string) $address->label;
    }

    private function persistComposedAddress(ShopAddressBook $book): void
    {
        $customer = $this->customer();
        $makePrimary = $customer->addresses()->doesntExist();

        $saved = $book->save($customer, [
            'label' => $this->addressLabel,
            'address_line' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'reference' => $this->deliveryNotes,
            'is_primary' => $makePrimary,
        ]);

        $this->applySavedAddress($saved);
    }

    private function customer(): ShopCustomer
    {
        $customer = ShopCustomer::current();

        abort_unless($customer instanceof ShopCustomer, 403);

        return $customer;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function branches(): Collection
    {
        return Branch::query()
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'city', 'state', 'phone']);
    }

    public function isPickup(): bool
    {
        return $this->fulfillment === ShopCheckoutData::FULFILLMENT_PICKUP;
    }

    public function selectFulfillment(string $mode): void
    {
        $this->fulfillment = $mode === ShopCheckoutData::FULFILLMENT_PICKUP
            ? ShopCheckoutData::FULFILLMENT_PICKUP
            : ShopCheckoutData::FULFILLMENT_DELIVERY;

        $this->resetValidation();
    }

    public function selectBranch(int $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function selectPaymentMethod(string $method): void
    {
        if (array_key_exists($method, ShopCheckoutData::paymentMethods())) {
            $this->paymentMethod = $method;
        }
    }

    public function nextStep(ShopAddressBook $book): void
    {
        $this->validate($this->rulesForStep($this->step), $this->messages());

        if ($this->step === self::STEP_FULFILLMENT && ! $this->isPickup() && $this->composingAddress) {
            $this->persistComposedAddress($book);
        }

        if ($this->step === self::STEP_FULFILLMENT && ! $this->isPickup() && $this->addressId) {
            $this->applySavedAddress($book->findOwned($this->customer(), $this->addressId));
        }

        $this->step = min(self::STEP_PAYMENT, $this->step + 1);
    }

    public function previousStep(): void
    {
        if ($this->step <= self::STEP_FULFILLMENT) {
            $this->redirectRoute('shop.cart', navigate: true);

            return;
        }

        $this->resetValidation();
        $this->step--;
    }

    public function placeOrder(): void
    {
        $this->validate($this->allRules(), $this->messages());

        if ($this->cart()->isEmpty()) {
            $this->dispatch('shop-toast', message: 'Tu carrito está vacío');
            $this->redirectRoute('shop.cart', navigate: true);

            return;
        }

        $this->placing = true;

        try {
            $order = app(ShopOrderPlacer::class)->place(ShopCheckoutData::fromArray([
                'name' => $this->name,
                'document_type' => $this->documentType,
                'document_number' => $this->documentNumber,
                'phone' => $this->phone,
                'email' => $this->email,
                'fulfillment' => $this->fulfillment,
                'payment_method' => $this->paymentMethod,
                'address' => $this->address,
                'city' => $this->city,
                'state' => $this->state,
                'branch_id' => $this->branchId,
                'delivery_notes' => $this->deliveryNotes,
                'notes' => $this->notes,
            ]));
        } catch (Throwable) {
            $this->placing = false;
            $this->dispatch('shop-toast', message: 'No pudimos crear tu pedido. Inténtalo de nuevo.');

            return;
        }

        session()->put('shop.checkout.last', [
            'name' => $this->name,
            'documentType' => $this->documentType,
            'documentNumber' => $this->documentNumber,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'addressId' => $this->addressId,
        ]);

        session()->push('shop.orders', $order->order_number);

        $this->cart()->clear();
        $this->placing = false;

        $this->redirectRoute('shop.order', ['order' => $order->order_number], navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            self::STEP_FULFILLMENT => $this->isPickup()
                ? ['branchId' => ['required', Rule::exists('branches', 'id')->where('is_active', true)]]
                : $this->deliveryRules(),
            self::STEP_CONTACT => [
                'name' => ['required', 'string', 'min:3', 'max:150'],
                'documentType' => ['required', Rule::in(['CC', 'CE', 'RIF', 'NIT', 'PAS'])],
                'documentNumber' => ['required', 'string', 'max:40'],
                'phone' => ['required', 'string', 'min:7', 'max:40'],
                'email' => ['required', 'email:rfc', 'max:180'],
            ],
            default => [
                'paymentMethod' => ['required', Rule::in(array_keys(ShopCheckoutData::paymentMethods()))],
                'notes' => ['nullable', 'string', 'max:500'],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function allRules(): array
    {
        return [
            ...$this->rulesForStep(self::STEP_FULFILLMENT),
            ...$this->rulesForStep(self::STEP_CONTACT),
            ...$this->rulesForStep(self::STEP_PAYMENT),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'address.required' => 'Necesitamos la dirección de entrega.',
            'address.min' => 'Escribe una dirección más específica.',
            'city.required' => 'Indica la ciudad.',
            'state.required' => 'Indica el estado.',
            'addressId.required' => 'Elige a qué dirección enviamos.',
            'addressLabel.max' => 'El nombre de la dirección es muy largo.',
            'branchId.required' => 'Elige la sucursal donde vas a retirar.',
            'name.required' => 'Dinos tu nombre completo.',
            'name.min' => 'Escribe tu nombre completo.',
            'documentNumber.required' => 'Falta tu número de documento.',
            'phone.required' => 'Necesitamos un teléfono de contacto.',
            'email.required' => 'Necesitamos tu correo para enviarte el pedido.',
            'email.email' => 'Ese correo no parece válido.',
        ];
    }

    public function render(): View
    {
        $cart = $this->cart();
        $lines = $cart->lines();

        return view('livewire.shop.checkout', [
            'lines' => $lines,
            'totals' => $cart->totals($lines),
            'branches' => $this->branches(),
            'savedAddresses' => $this->savedAddresses(),
            'paymentMethods' => ShopCheckoutData::paymentMethods(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryRules(): array
    {
        if ($this->composingAddress || $this->addressId === null) {
            return [
                'addressLabel' => ['nullable', 'string', 'max:40'],
                'address' => ['required', 'string', 'min:8', 'max:255'],
                'city' => ['required', 'string', 'max:120'],
                'state' => ['required', 'string', 'max:120'],
                'deliveryNotes' => ['nullable', 'string', 'max:500'],
            ];
        }

        return [
            'addressId' => [
                'required',
                'integer',
                Rule::exists('pwa_addresses', 'id')->where('pwa_customer_id', $this->customer()->id),
            ],
        ];
    }
}
