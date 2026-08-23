<?php

namespace App\Services\Shop;

use App\Models\ShopAddress;
use App\Models\ShopCustomer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ShopAddressBook
{
    /**
     * @param  array{
     *     label?: string|null,
     *     address_line: string,
     *     city: string,
     *     state: string,
     *     reference?: string|null,
     *     is_primary?: bool
     * }  $data
     */
    public function save(ShopCustomer $customer, array $data, ?ShopAddress $address = null): ShopAddress
    {
        return DB::transaction(function () use ($customer, $data, $address): ShopAddress {
            $owned = $address instanceof ShopAddress
                ? $this->owned($customer, $address)
                : new ShopAddress(['pwa_customer_id' => $customer->id]);

            $excludeCurrent = function (HasMany $query) use ($owned): HasMany {
                return $owned->exists
                    ? $query->whereKeyNot($owned->id)
                    : $query;
            };

            $isFirst = $excludeCurrent($customer->addresses())->doesntExist();
            $hasOtherPrimary = $excludeCurrent($customer->addresses())->where('is_primary', true)->exists();
            $makePrimary = $isFirst || (bool) ($data['is_primary'] ?? false);

            if (! $makePrimary && $owned->exists && $owned->is_primary && ! $hasOtherPrimary) {
                $makePrimary = true;
            }

            $owned->fill([
                'label' => filled($data['label'] ?? null) ? trim((string) $data['label']) : null,
                'address_line' => trim($data['address_line']),
                'city' => trim($data['city']),
                'state' => trim($data['state']),
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'is_primary' => $makePrimary,
            ]);
            $owned->save();

            if ($makePrimary) {
                $this->clearOtherPrimaries($customer, $owned);
            }

            return $owned->refresh();
        });
    }

    public function markPrimary(ShopCustomer $customer, ShopAddress $address): ShopAddress
    {
        return DB::transaction(function () use ($customer, $address): ShopAddress {
            $owned = $this->owned($customer, $address);
            $owned->forceFill(['is_primary' => true])->save();
            $this->clearOtherPrimaries($customer, $owned);

            return $owned->refresh();
        });
    }

    public function delete(ShopCustomer $customer, ShopAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            $owned = $this->owned($customer, $address);
            $wasPrimary = $owned->is_primary;
            $owned->delete();

            if (! $wasPrimary) {
                return;
            }

            $next = $customer->addresses()->reorder()->orderByDesc('id')->first();

            if ($next instanceof ShopAddress) {
                $next->forceFill(['is_primary' => true])->save();
            }
        });
    }

    public function findOwned(ShopCustomer $customer, int $id): ShopAddress
    {
        $address = $customer->addresses()->whereKey($id)->first();

        if (! $address instanceof ShopAddress) {
            throw ValidationException::withMessages([
                'addressId' => 'Esa dirección ya no está disponible.',
            ]);
        }

        return $address;
    }

    private function owned(ShopCustomer $customer, ShopAddress $address): ShopAddress
    {
        if ((int) $address->pwa_customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'addressId' => 'Esa dirección no te pertenece.',
            ]);
        }

        return $address;
    }

    private function clearOtherPrimaries(ShopCustomer $customer, ShopAddress $address): void
    {
        $customer->addresses()
            ->whereKeyNot($address->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
