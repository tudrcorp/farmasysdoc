<?php

namespace App\Jobs;

use App\Models\EmployeeRequest;
use App\Services\Hr\EmployeeRequestDecisionNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Avisa al empleado por un canal (correo o WhatsApp). Un job por canal para que el
 * reintento de uno no repita el envío del otro.
 */
class NotifyEmployeeRequestDecisionJob implements ShouldQueue
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

    public function __construct(public int $employeeRequestId, public string $channel)
    {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function handle(EmployeeRequestDecisionNotifier $notifier): void
    {
        $request = EmployeeRequest::query()->with('employee')->find($this->employeeRequestId);

        if (! $request instanceof EmployeeRequest) {
            return;
        }

        match ($this->channel) {
            self::CHANNEL_WHATSAPP => $notifier->sendWhatsApp($request),
            default => $notifier->sendEmail($request),
        };
    }
}
