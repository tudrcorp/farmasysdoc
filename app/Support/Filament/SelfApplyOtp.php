<?php

namespace App\Support\Filament;

use App\Models\User;
use App\Services\Otp\OtpCodeService;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

/**
 * Check «Usar OTP» de los modales que piden clave: con el permiso «Usar OTP automática» el
 * usuario usa la clave que solicitó sin esperar a un tercero. El campo de 6 dígitos se oculta
 * y al enviar se manda {@see OtpCodeService::SELF_APPLY_TOKEN}.
 */
final class SelfApplyOtp
{
    public static function fieldName(string $codeField): string
    {
        return $codeField.'_self_apply';
    }

    public static function checkbox(string $codeField): Checkbox
    {
        return Checkbox::make(self::fieldName($codeField))
            ->label('Usar OTP')
            ->helperText('Usa la clave que usted solicitó sin esperar a un gerente o administrador. Queda registrado en «Claves OTP» y se avisa a los administradores.')
            ->default(false)
            ->live()
            ->visible(fn (): bool => self::userCanSelfApply())
            ->columnSpanFull();
    }

    public static function userCanSelfApply(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canSelfApplyOtp();
    }

    /**
     * ¿Está marcado el check en el formulario?
     */
    public static function isChecked(Get $get, string $codeField): bool
    {
        return self::userCanSelfApply() && filter_var($get(self::fieldName($codeField)), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Clave a verificar: el token de uso automático si el check está marcado, o los dígitos escritos.
     *
     * @param  array<string, mixed>  $data
     */
    public static function resolve(array $data, string $codeField): ?string
    {
        if (filter_var($data[self::fieldName($codeField)] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return OtpCodeService::SELF_APPLY_TOKEN;
        }

        return self::digits($data[$codeField] ?? null);
    }

    /**
     * Normaliza el valor del campo de 6 dígitos (puede llegar como arreglo de dígitos).
     */
    public static function digits(mixed $raw): ?string
    {
        if (is_array($raw)) {
            $raw = implode('', array_map(static fn (mixed $digit): string => (string) $digit, $raw));
        }

        return $raw !== null && $raw !== '' ? (string) $raw : null;
    }
}
