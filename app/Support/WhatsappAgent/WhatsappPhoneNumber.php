<?php

namespace App\Support\WhatsappAgent;

/**
 * Teléfonos de WhatsApp: se guardan solo con dígitos y código de país (584121234567).
 */
final class WhatsappPhoneNumber
{
    private const VENEZUELA_CODE = '58';

    /**
     * Acepta «584121234567@c.us», «+58 412-123.45.67», «04121234567» o «4121234567».
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) preg_replace('/@.*$/', '', trim($value));
        $digits = (string) preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return self::VENEZUELA_CODE.substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '4')) {
            return self::VENEZUELA_CODE.$digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * Formato local venezolano (04121234567); otros países quedan con «+».
     */
    public static function toLocal(string $phone): string
    {
        $digits = self::normalize($phone) ?? $phone;

        if (str_starts_with($digits, self::VENEZUELA_CODE) && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        return '+'.$digits;
    }

    public static function isGroupChat(string $chatId): bool
    {
        return str_ends_with(trim($chatId), '@g.us');
    }
}
