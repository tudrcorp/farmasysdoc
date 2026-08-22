<?php

namespace App\Jobs;

use App\Models\AccountsPayable;
use App\Services\Finance\AccountsPayablePaymentReportMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAccountsPayablePaymentReportMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $accountsPayableId)
    {
        $this->afterCommit();
    }

    public function handle(AccountsPayablePaymentReportMailer $mailer): void
    {
        $accountsPayable = AccountsPayable::query()->find($this->accountsPayableId);
        if (! $accountsPayable instanceof AccountsPayable) {
            return;
        }

        $mailer->send($accountsPayable);
    }
}
