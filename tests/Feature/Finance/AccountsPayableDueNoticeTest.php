<?php

use App\Jobs\SendAccountsPayableDueNoticeJob;
use App\Models\AccountsPayable;
use App\Models\AccountsPayableDueNotice;
use App\Models\User;
use App\Services\Finance\AccountsPayableDueNoticeService;
use App\Support\Finance\AccountsPayableStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    Notification::fake();

    $this->today = CarbonImmutable::today();
    $this->admin = User::factory()->create(['roles' => ['ADMINISTRADOR'], 'whatsapp_phone' => '584141234567']);
    $this->service = app(AccountsPayableDueNoticeService::class);
});

test('administrators get the 5 and 3 day notices by mail and WhatsApp with the PDF', function (): void {
    AccountsPayable::factory()->dueInDays(5)->create();
    AccountsPayable::factory()->dueInDays(3)->create();

    $result = $this->service->run($this->today);

    expect($result['sent'])->toBeTrue()
        ->and($result['buckets'])->toBe([5 => 1, 3 => 1]);

    Storage::disk('local')->assertExists($result['pdf_path']);

    Queue::assertPushed(SendAccountsPayableDueNoticeJob::class, fn (SendAccountsPayableDueNoticeJob $job): bool => $job->userId === $this->admin->id
        && $job->channel === SendAccountsPayableDueNoticeJob::CHANNEL_MAIL
        && $job->pdfPath === $result['pdf_path']);
    Queue::assertPushed(SendAccountsPayableDueNoticeJob::class, fn (SendAccountsPayableDueNoticeJob $job): bool => $job->channel === SendAccountsPayableDueNoticeJob::CHANNEL_WHATSAPP);
});

test('nothing is sent when no invoice reaches a notice threshold', function (): void {
    AccountsPayable::factory()->dueInDays(10)->create();
    AccountsPayable::factory()->dueInDays(-4)->create();

    expect($this->service->run($this->today)['sent'])->toBeFalse();
    Queue::assertNothingPushed();
});

test('each invoice is notified once per threshold', function (): void {
    $payable = AccountsPayable::factory()->dueInDays(5)->create();

    expect($this->service->run($this->today)['sent'])->toBeTrue()
        ->and($this->service->run($this->today)['sent'])->toBeFalse();

    $threeDaysBefore = $this->today->addDays(2);
    expect($this->service->run($threeDaysBefore)['buckets'])->toBe([3 => 1])
        ->and(AccountsPayableDueNotice::query()->where('accounts_payable_id', $payable->id)->pluck('threshold_days')->sort()->values()->all())->toBe([3, 5]);
});

test('a missed day is caught up on the next run', function (): void {
    AccountsPayable::factory()->dueInDays(2)->create();

    $result = $this->service->run($this->today);

    expect($result['buckets'])->toBe([3 => 1])
        ->and(AccountsPayableDueNotice::query()->count())->toBe(2);
});

test('paid or annulled invoices are not notified', function (): void {
    AccountsPayable::factory()->dueInDays(5)->create(['status' => AccountsPayableStatus::PAGADO]);
    AccountsPayable::factory()->dueInDays(3)->create(['status' => AccountsPayableStatus::ANULADA]);

    expect($this->service->run($this->today)['sent'])->toBeFalse();
});

test('overdue invoices are included in the report', function (): void {
    AccountsPayable::factory()->dueInDays(5)->create();
    AccountsPayable::factory()->dueInDays(-10)->create();

    expect($this->service->run($this->today)['overdue'])->toBe(1);
});

test('a changed due date is notified again', function (): void {
    $payable = AccountsPayable::factory()->dueInDays(5)->create();
    $this->service->run($this->today);

    $payable->update(['due_at' => $this->today->addDays(4)->toDateString()]);

    expect($this->service->run($this->today)['sent'])->toBeTrue();
});
