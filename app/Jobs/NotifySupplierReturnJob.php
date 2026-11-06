<?php

namespace App\Jobs;

use App\Enums\SupplierReturnEventType;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\Purchases\SupplierReturnNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Aviso de devolución a un destinatario por un canal (un job por canal para no repetir envíos al reintentar).
 */
class NotifySupplierReturnJob implements ShouldQueue
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

    public function __construct(
        public int $supplierReturnId,
        public int $userId,
        public string $event,
        public string $channel,
    ) {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function handle(SupplierReturnNotifier $notifier): void
    {
        $return = SupplierReturn::query()->with(['branch', 'supplier', 'items'])->find($this->supplierReturnId);
        $user = User::query()->find($this->userId);
        $event = SupplierReturnEventType::tryFrom($this->event);

        if (! $return instanceof SupplierReturn || ! $user instanceof User || $event === null) {
            return;
        }

        match ($this->channel) {
            self::CHANNEL_WHATSAPP => $notifier->sendWhatsApp($return, $user, $event),
            default => $notifier->sendEmail($return, $user, $event),
        };
    }
}
