<?php

namespace App\Services\Otp;

use App\Filament\Resources\OtpCodes\OtpCodeResource;
use App\Models\OtpCode;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa en la campana del panel a los administradores cuando alguien usa una OTP con «Usar OTP»
 * (sin que un tercero se la indique).
 */
final class OtpSelfApplyNotifier
{
    public function notify(OtpCode $otp, User $user): void
    {
        $body = implode(' ', array_filter([
            "{$user->name} usó su propia clave de «{$otp->purpose->label()}»",
            filled($otp->used_in) ? "en {$otp->used_in}." : '.',
            filled($otp->summary) ? "Detalle: {$otp->summary}." : null,
        ]));

        $recipients = User::query()->get()
            ->filter(fn (User $admin): bool => $admin->isAdministrator() && $admin->getKey() !== $user->getKey());

        foreach ($recipients as $admin) {
            try {
                Notification::make()
                    ->title('OTP usada sin autorización de un tercero')
                    ->body($body)
                    ->warning()
                    ->icon('heroicon-o-key')
                    ->actions([
                        Action::make('openOtpCodes')
                            ->label('Ver claves OTP')
                            ->url(OtpCodeResource::getUrl('index', ['filters' => ['self_applied' => ['value' => 1]]], panel: 'farmaadmin'))
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($admin);
            } catch (Throwable $exception) {
                Log::warning('OTP automática: no se pudo guardar el aviso de panel', [
                    'otp_code_id' => $otp->getKey(),
                    'user_id' => $admin->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
