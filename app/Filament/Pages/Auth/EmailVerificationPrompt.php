<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt as BaseEmailVerificationPrompt;
use Filament\Notifications\Notification;

class EmailVerificationPrompt extends BaseEmailVerificationPrompt
{
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}

    public function resendNotificationAction(): Action
    {
        return Action::make('resendNotification')
            ->link()
            ->label(__('filament-panels::auth/pages/email-verification/email-verification-prompt.actions.resend_notification.label').'.')
            ->size('sm')
            ->action(function (): void {
                $this->sendEmailVerificationNotification($this->getVerifiable());

                Notification::make()
                    ->title(__('filament-panels::auth/pages/email-verification/email-verification-prompt.notifications.notification_resent.title'))
                    ->success()
                    ->send();
            });
    }
}
