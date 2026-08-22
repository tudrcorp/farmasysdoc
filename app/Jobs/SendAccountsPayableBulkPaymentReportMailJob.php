<?php

namespace App\Jobs;

use App\Models\AccountsPayable;
use App\Services\Finance\AccountsPayablePaymentReportMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAccountsPayableBulkPaymentReportMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $accountsPayableIds
     */
    public function __construct(public array $accountsPayableIds)
    {
        $this->afterCommit();
    }

    public function handle(AccountsPayablePaymentReportMailer $mailer): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $this->accountsPayableIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return;
        }

        $records = AccountsPayable::query()
            ->whereKey($ids)
            ->with(['purchase.supplier', 'branch', 'purchase'])
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        $mailer->sendMany($records);
    }
}
