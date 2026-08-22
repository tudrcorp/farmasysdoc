<?php

namespace App\Livewire\Storefront;

use App\Models\Branch;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Shop\ShopCart;
use App\Support\Shop\ShopCheckoutData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.storefront-pay')]
#[Title('Pago seguro')]
class Pay extends Component
{
    public string $fulfillment = ShopCheckoutData::FULFILLMENT_DELIVERY;

    public string $paymentMethod = 'pago_movil';

    public ?int $branchId = null;

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $deliveryNotes = '';

    public bool $ready = false;

    public function mount(): void
    {
        if ($this->cart()->isEmpty()) {
            $this->redirectRoute('home');

            return;
        }

        $this->branchId ??= $this->branches()->first()?->id;

        $saved = session('storefront.checkout', []);

        if (is_array($saved)) {
            if (isset($saved['fulfillment']) && is_string($saved['fulfillment'])) {
                $this->selectFulfillment($saved['fulfillment']);
            }

            if (isset($saved['paymentMethod']) && is_string($saved['paymentMethod'])) {
                $this->selectPaymentMethod($saved['paymentMethod']);
            }

            foreach (['address', 'city', 'state', 'deliveryNotes'] as $field) {
                if (isset($saved[$field]) && is_string($saved[$field])) {
                    $this->{$field} = $saved[$field];
                }
            }

            if (isset($saved['branchId']) && is_numeric($saved['branchId'])) {
                $this->branchId = (int) $saved['branchId'];
            }
        }
    }

    public function cart(): ShopCart
    {
        return app(ShopCart::class);
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

        $this->ready = false;
        $this->resetValidation();
    }

    public function selectPaymentMethod(string $method): void
    {
        if (! array_key_exists($method, ShopCheckoutData::webPaymentMethods())) {
            return;
        }

        $this->paymentMethod = $method;
        $this->ready = false;
    }

    public function selectBranch(int $branchId): void
    {
        $this->branchId = $branchId;
        $this->ready = false;
    }

    public function continueSecurely(): void
    {
        $this->validate($this->rules(), $this->messages());

        session()->put('storefront.checkout', [
            'fulfillment' => $this->fulfillment,
            'paymentMethod' => $this->paymentMethod,
            'branchId' => $this->branchId,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'deliveryNotes' => $this->deliveryNotes,
        ]);

        $this->ready = true;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = [
            'paymentMethod' => ['required', Rule::in(array_keys(ShopCheckoutData::webPaymentMethods()))],
            'fulfillment' => ['required', Rule::in([
                ShopCheckoutData::FULFILLMENT_DELIVERY,
                ShopCheckoutData::FULFILLMENT_PICKUP,
            ])],
        ];

        if ($this->isPickup()) {
            $rules['branchId'] = ['required', Rule::exists('branches', 'id')->where('is_active', true)];

            return $rules;
        }

        return [
            ...$rules,
            'address' => ['required', 'string', 'min:8', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'deliveryNotes' => ['nullable', 'string', 'max:500'],
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
            'branchId.required' => 'Elige la sucursal donde vas a retirar.',
            'paymentMethod.required' => 'Elige un método de pago.',
        ];
    }

    public function render(): View
    {
        $cart = $this->cart();
        $lines = $cart->lines();
        $totals = $cart->totals($lines);
        $rate = null;

        try {
            $rate = app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now());
        } catch (Throwable) {
            $rate = null;
        }

        return view('livewire.storefront.pay', [
            'lines' => $lines,
            'totals' => $totals,
            'branches' => $this->branches(),
            'paymentMethods' => ShopCheckoutData::webPaymentMethods(),
            'usdVesRate' => $rate,
            'vesTotal' => $rate !== null ? round($totals['total'] * $rate, 2) : null,
        ]);
    }
}
