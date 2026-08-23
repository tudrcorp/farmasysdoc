<?php

namespace App\Support\Shop;

use App\Enums\ShopIdentityMethod;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Validation\ValidationException;

final class ShopCustomerIdentity
{
    public const SESSION_DRAFT = 'shop.register.draft';

    public const SESSION_JUST_REGISTERED = 'shop.just_registered';

    /**
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     method: string,
     *     document_type: string,
     *     document_number: string,
     *     phone: string
     * }
     */
    public static function emptyDraft(): array
    {
        return [
            'first_name' => '',
            'last_name' => '',
            'method' => ShopIdentityMethod::Document->value,
            'document_type' => 'V',
            'document_number' => '',
            'phone' => '',
        ];
    }

    /**
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     method: string,
     *     document_type: string,
     *     document_number: string,
     *     phone: string
     * }
     */
    public static function draft(): array
    {
        $stored = session(self::SESSION_DRAFT, []);

        if (! is_array($stored)) {
            return self::emptyDraft();
        }

        return [
            ...self::emptyDraft(),
            ...array_intersect_key($stored, self::emptyDraft()),
        ];
    }

    /**
     * @param  array{
     *     first_name?: string,
     *     last_name?: string,
     *     method?: string,
     *     document_type?: string,
     *     document_number?: string,
     *     phone?: string
     * }  $draft
     */
    public static function putDraft(array $draft): void
    {
        session([self::SESSION_DRAFT => [
            ...self::emptyDraft(),
            ...array_intersect_key($draft, self::emptyDraft()),
        ]]);
    }

    public static function forgetDraft(): void
    {
        session()->forget(self::SESSION_DRAFT);
    }

    public static function normalizeDocumentNumber(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function normalizePhone(string $value): ?string
    {
        return WhatsAppLink::normalizePhoneDigits($value);
    }

    public static function assertValidPhone(?string $digits): string
    {
        if ($digits === null || ! preg_match('/^58[24]\d{9}$/', $digits)) {
            throw ValidationException::withMessages([
                'phone' => 'Escribe un celular venezolano válido, por ejemplo 0412-1234567.',
            ]);
        }

        return $digits;
    }

    public static function displayPhone(?string $digits): string
    {
        if (! filled($digits)) {
            return '';
        }

        $local = str_starts_with((string) $digits, '58')
            ? '0'.substr((string) $digits, 2)
            : (string) $digits;

        if (strlen($local) === 11) {
            return substr($local, 0, 4).'-'.substr($local, 4);
        }

        return $local;
    }
}
