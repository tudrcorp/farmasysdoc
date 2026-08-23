<?php

namespace App\Livewire\Shop;

use App\Models\ShopAddress;
use App\Models\ShopCustomer;
use App\Services\Shop\ShopAddressBook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class AccountAddresses extends Component
{
    public bool $composing = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    public string $label = '';

    public string $line = '';

    public string $city = '';

    public string $state = '';

    public string $reference = '';

    public bool $isPrimary = false;

    public function startCreate(): void
    {
        $this->resetForm();
        $this->composing = true;
        $this->isPrimary = $this->addresses()->isEmpty();
    }

    public function startEdit(int $id, ShopAddressBook $book): void
    {
        $address = $book->findOwned($this->customer(), $id);

        $this->resetValidation();
        $this->composing = true;
        $this->editingId = $address->id;
        $this->confirmingDeleteId = null;
        $this->label = (string) $address->label;
        $this->line = (string) $address->address_line;
        $this->city = (string) $address->city;
        $this->state = (string) $address->state;
        $this->reference = (string) $address->reference;
        $this->isPrimary = $address->is_primary;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(ShopAddressBook $book): void
    {
        $this->validate([
            'label' => ['nullable', 'string', 'max:40'],
            'line' => ['required', 'string', 'min:8', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:500'],
        ], [
            'line.required' => 'Escribe la dirección de entrega.',
            'line.min' => 'Escribe una dirección más específica.',
            'city.required' => 'Indica la ciudad.',
            'state.required' => 'Indica el estado.',
        ]);

        $customer = $this->customer();
        $editing = $this->editingId
            ? $book->findOwned($customer, $this->editingId)
            : null;

        $book->save($customer, [
            'label' => $this->label,
            'address_line' => $this->line,
            'city' => $this->city,
            'state' => $this->state,
            'reference' => $this->reference,
            'is_primary' => $this->isPrimary,
        ], $editing);

        $this->resetForm();
        $this->dispatch('shop-toast', message: 'Dirección guardada');
    }

    public function markPrimary(int $id, ShopAddressBook $book): void
    {
        $book->markPrimary($this->customer(), $book->findOwned($this->customer(), $id));
        $this->dispatch('shop-toast', message: 'Quedó como dirección principal');
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function deleteConfirmed(ShopAddressBook $book): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $book->delete(
            $this->customer(),
            $book->findOwned($this->customer(), $this->confirmingDeleteId),
        );

        if ($this->editingId === $this->confirmingDeleteId) {
            $this->resetForm();
        }

        $this->confirmingDeleteId = null;
        $this->dispatch('shop-toast', message: 'Dirección eliminada');
    }

    /**
     * @return Collection<int, ShopAddress>
     */
    public function addresses(): Collection
    {
        return $this->customer()->addresses()->get();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->composing = false;
        $this->editingId = null;
        $this->confirmingDeleteId = null;
        $this->label = '';
        $this->line = '';
        $this->city = '';
        $this->state = '';
        $this->reference = '';
        $this->isPrimary = false;
    }

    private function customer(): ShopCustomer
    {
        $customer = ShopCustomer::current();

        abort_unless($customer instanceof ShopCustomer, 403);

        return $customer;
    }

    public function render(): View
    {
        return view('livewire.shop.account-addresses', [
            'addresses' => $this->addresses(),
        ]);
    }
}
