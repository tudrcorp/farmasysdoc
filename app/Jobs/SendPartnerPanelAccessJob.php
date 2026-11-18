<?php

namespace App\Jobs;

use App\Mail\PartnerPanelAccessMail;
use App\Models\User;
use App\Support\Partners\PartnerPanel;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Genera el enlace de un solo uso del panel de aliados y lo envía por correo. El token se crea
 * dentro del job (no viaja en la cola) y cada envío invalida el enlace anterior.
 */
class SendPartnerPanelAccessJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        public int $userId,
        public bool $isInvitation = true,
    ) {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function handle(): void
    {
        $user = User::query()->with('partnerCompany')->find($this->userId);

        if (! $user instanceof User || ! $user->canAccessPartnerPanel()) {
            return;
        }

        $token = Password::broker(PartnerPanel::PASSWORD_BROKER)->createToken($user);
        $url = Filament::getPanel(User::PARTNER_PANEL_ID)->getResetPasswordUrl($token, $user);

        Mail::to($user->email)->send(new PartnerPanelAccessMail(
            recipientName: (string) $user->name,
            companyName: (string) ($user->partnerCompany?->trade_name ?: $user->partnerCompany?->legal_name),
            email: (string) $user->email,
            actionUrl: $url,
            isInvitation: $this->isInvitation,
            expiresInHours: PartnerPanel::accessLinkHours(),
        ));
    }
}
