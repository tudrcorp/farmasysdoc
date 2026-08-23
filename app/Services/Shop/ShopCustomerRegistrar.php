<?php

namespace App\Services\Shop;

use App\Enums\ShopIdentityMethod;
use App\Models\ShopCustomer;
use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class ShopCustomerRegistrar
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     method: string,
     *     document_type: string,
     *     document_number: string,
     *     phone: string
     * }  $draft
     */
    public function registerWithPassword(array $draft, string $password): ShopCustomer
    {
        $method = ShopIdentityMethod::from($draft['method']);
        $this->assertIdentityIsFree($method, $draft);

        $customer = ShopCustomer::query()->create([
            'first_name' => $draft['first_name'],
            'last_name' => $draft['last_name'],
            'document_type' => $method === ShopIdentityMethod::Document ? $draft['document_type'] : null,
            'document_number' => $method === ShopIdentityMethod::Document ? $draft['document_number'] : null,
            'phone' => $method === ShopIdentityMethod::Phone ? $draft['phone'] : null,
            'password' => $password,
        ]);

        $this->login($customer, justRegistered: true);

        return $customer;
    }

    /**
     * @param  array{id: string, email: ?string, first_name: string, last_name: ?string, avatar: ?string}  $google
     * @return array{customer: ShopCustomer, created: bool}
     */
    public function registerOrLoginWithGoogle(array $google): array
    {
        $customer = ShopCustomer::query()
            ->where('google_id', $google['id'])
            ->first();

        if ($customer instanceof ShopCustomer) {
            $this->login($customer, justRegistered: false);

            return ['customer' => $customer, 'created' => false];
        }

        if (filled($google['email'])) {
            $customer = ShopCustomer::query()
                ->where('email', $google['email'])
                ->first();

            if ($customer instanceof ShopCustomer) {
                $customer->forceFill([
                    'google_id' => $google['id'],
                    'google_avatar' => $google['avatar'],
                    'email' => $google['email'],
                ])->save();

                $this->login($customer, justRegistered: false);

                return ['customer' => $customer, 'created' => false];
            }
        }

        $customer = ShopCustomer::query()->create([
            'first_name' => $google['first_name'],
            'last_name' => $google['last_name'],
            'email' => $google['email'],
            'google_id' => $google['id'],
            'google_avatar' => $google['avatar'],
        ]);

        $this->login($customer, justRegistered: true);

        return ['customer' => $customer, 'created' => true];
    }

    public function login(ShopCustomer $customer, bool $justRegistered = false): void
    {
        Auth::guard('shop')->login($customer, remember: true);
        ShopCustomerIdentity::forgetDraft();

        if ($justRegistered) {
            session([ShopCustomerIdentity::SESSION_JUST_REGISTERED => true]);
        }
    }

    /**
     * @param  array{document_type: string, document_number: string, phone: string}  $draft
     */
    public function assertIdentityIsFree(ShopIdentityMethod $method, array $draft): void
    {
        if ($method === ShopIdentityMethod::Document) {
            $exists = ShopCustomer::query()
                ->where('document_type', $draft['document_type'])
                ->where('document_number', $draft['document_number'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'document_number' => 'Ya hay una cuenta con esa cédula. Entra con “Ya tengo cuenta”.',
                ]);
            }

            return;
        }

        $exists = ShopCustomer::query()
            ->where('phone', $draft['phone'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'phone' => 'Ya hay una cuenta con ese teléfono. Entra con “Ya tengo cuenta”.',
            ]);
        }
    }
}
