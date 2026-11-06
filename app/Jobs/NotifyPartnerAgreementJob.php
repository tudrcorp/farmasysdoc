<?php

namespace App\Jobs;

use App\Enums\PartnerAgreementEventType;
use App\Models\PartnerCompanyAgreement;
use App\Models\User;
use App\Services\Partners\PartnerAgreementNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envía un aviso de convenio a un destinatario por un canal. Un job por canal para que el reintento
 * de uno no repita el envío del otro.
 */
class NotifyPartnerAgreementJob implements ShouldQueue
{
    use Queueable;

    public const string CHANNEL_MAIL = 'mail';

    public const string CHANNEL_WHATSAPP = 'whatsapp';

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $agreementId,
        public int $userId,
        public string $event,
        public string $channel,
        public array $context = [],
    ) {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function handle(PartnerAgreementNotifier $notifier): void
    {
        $agreement = PartnerCompanyAgreement::query()->with('partnerCompany')->find($this->agreementId);
        $user = User::query()->find($this->userId);
        $event = PartnerAgreementEventType::tryFrom($this->event);

        if (! $agreement instanceof PartnerCompanyAgreement || ! $user instanceof User || $event === null) {
            return;
        }

        match ($this->channel) {
            self::CHANNEL_WHATSAPP => $notifier->sendWhatsApp($agreement, $user, $event, $this->context),
            default => $notifier->sendEmail($agreement, $user, $event, $this->context),
        };
    }
}
