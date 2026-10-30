<?php

namespace App\Jobs;

use App\Models\TrainingEnrollment;
use App\Services\Training\TrainingEnrollmentNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Avisa al empleado por un canal. Un job por canal para que el reintento
 * de uno no repita el envío del otro.
 */
class NotifyTrainingEnrollmentJob implements ShouldQueue
{
    use Queueable;

    public const string CHANNEL_MAIL = 'mail';

    public const string CHANNEL_WHATSAPP = 'whatsapp';

    public const string EVENT_ENROLLED = 'enrolled';

    public const string EVENT_CANCELLED = 'cancelled';

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public int $enrollmentId, public string $event, public string $channel)
    {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function handle(TrainingEnrollmentNotifier $notifier): void
    {
        $enrollment = TrainingEnrollment::query()->with(['employee', 'session.course'])->find($this->enrollmentId);

        if (! $enrollment instanceof TrainingEnrollment) {
            return;
        }

        match ($this->channel) {
            self::CHANNEL_WHATSAPP => $notifier->sendWhatsApp($enrollment, $this->event),
            default => $notifier->sendEmail($enrollment, $this->event),
        };
    }
}
