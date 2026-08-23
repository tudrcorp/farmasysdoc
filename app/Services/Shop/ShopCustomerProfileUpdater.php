<?php

namespace App\Services\Shop;

use App\Models\ShopCustomer;
use App\Support\Shop\ShopCustomerIdentity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class ShopCustomerProfileUpdater
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     document_type: string,
     *     document_number: string,
     *     phone: string
     * }  $data
     */
    public function update(ShopCustomer $customer, array $data): ShopCustomer
    {
        $documentType = $data['document_type'];
        $documentNumber = ShopCustomerIdentity::normalizeDocumentNumber($data['document_number']);
        $phone = ShopCustomerIdentity::assertValidPhone(
            ShopCustomerIdentity::normalizePhone($data['phone']),
        );

        $this->assertDocumentIsFree($customer, $documentType, $documentNumber);
        $this->assertPhoneIsFree($customer, $phone);

        $customer->forceFill([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'phone' => $phone,
        ])->save();

        $customer = $customer->refresh();
        Auth::guard('shop')->setUser($customer);

        return $customer;
    }

    private function assertDocumentIsFree(ShopCustomer $customer, string $documentType, string $documentNumber): void
    {
        $taken = ShopCustomer::query()
            ->where('document_type', $documentType)
            ->where('document_number', $documentNumber)
            ->whereKeyNot($customer->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'documentNumber' => 'Ya hay otra cuenta con esa cédula.',
            ]);
        }
    }

    private function assertPhoneIsFree(ShopCustomer $customer, string $phone): void
    {
        $taken = ShopCustomer::query()
            ->where('phone', $phone)
            ->whereKeyNot($customer->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'phone' => 'Ya hay otra cuenta con ese teléfono.',
            ]);
        }
    }
}
