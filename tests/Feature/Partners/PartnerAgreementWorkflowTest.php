<?php

use App\Enums\PartnerAgreementEventType;
use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Jobs\NotifyPartnerAgreementJob;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Models\User;
use App\Services\Partners\PartnerAgreementException;
use App\Services\Partners\PartnerAgreementLifecycleRunner;
use App\Services\Partners\PartnerAgreementWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('partner-companies/agreements/contrato.pdf', 'pdf');
    Queue::fake();
    Notification::fake();

    $this->today = CarbonImmutable::today();
    $this->admin = User::factory()->create(['roles' => ['ADMINISTRADOR'], 'whatsapp_phone' => '584141234567']);
    $this->workflow = app(PartnerAgreementWorkflow::class);
    $this->partner = PartnerCompany::factory()->onCredit(1000)->create(['assigned_credit_limit' => 600]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agreementData(PartnerCompany $partner, array $overrides = []): array
{
    return [
        'partner_company_id' => $partner->id,
        'type' => PartnerAgreementType::Initial->value,
        'starts_on' => CarbonImmutable::today()->toDateString(),
        'ends_on' => CarbonImmutable::today()->addYear()->subDay()->toDateString(),
        'payment_modality' => 'credito',
        'credit_limit_usd' => 1000,
        'discount_percentage' => 5,
        'signed_document_path' => 'partner-companies/agreements/contrato.pdf',
        'signed_document_name' => 'contrato.pdf',
        ...$overrides,
    ];
}

function activeAgreement(PartnerAgreementWorkflow $workflow, User $admin, array $data): PartnerCompanyAgreement
{
    $agreement = $workflow->createDraft($data, $admin);
    $workflow->submit($agreement, $admin);

    return $workflow->approve($agreement->fresh(), $admin);
}

test('an approved initial agreement becomes active and copies its terms to the partner', function (): void {
    $agreement = activeAgreement($this->workflow, $this->admin, agreementData($this->partner, ['discount_percentage' => 8]));

    $this->partner->refresh();

    expect($agreement->status)->toBe(PartnerAgreementStatus::Active)
        ->and($agreement->number)->toStartWith('CONV-')
        ->and($this->partner->agreement_reference)->toBe($agreement->number)
        ->and((float) $this->partner->discount_percentage)->toBe(8.0)
        ->and((float) $this->partner->assigned_credit_limit)->toBe(600.0)
        ->and($agreement->events()->pluck('type')->all())->toContain(
            PartnerAgreementEventType::Created,
            PartnerAgreementEventType::Submitted,
            PartnerAgreementEventType::Approved,
            PartnerAgreementEventType::Activated,
            PartnerAgreementEventType::TermsApplied,
        );
});

test('an amendment supersedes the active agreement and moves the balance by the limit difference', function (): void {
    $initial = activeAgreement($this->workflow, $this->admin, agreementData($this->partner));

    $amendment = activeAgreement($this->workflow, $this->admin, agreementData($this->partner, [
        'type' => PartnerAgreementType::Amendment->value,
        'change_reason' => 'Ampliación de cupo',
        'credit_limit_usd' => 1500,
        'ends_on' => $initial->ends_on->toDateString(),
    ]));

    $this->partner->refresh();

    expect($amendment->previous_agreement_id)->toBe($initial->id)
        ->and($initial->fresh()->status)->toBe(PartnerAgreementStatus::Superseded)
        ->and((float) $this->partner->credit_limit_usd)->toBe(1500.0)
        ->and((float) $this->partner->assigned_credit_limit)->toBe(1100.0);
});

test('lowering the limit below what was consumed leaves the balance at zero', function (): void {
    $initial = activeAgreement($this->workflow, $this->admin, agreementData($this->partner));

    activeAgreement($this->workflow, $this->admin, agreementData($this->partner, [
        'type' => PartnerAgreementType::Renegotiation->value,
        'change_reason' => 'Reducción de cupo',
        'credit_limit_usd' => 200,
        'ends_on' => $initial->ends_on->toDateString(),
    ]));

    expect((float) $this->partner->fresh()->assigned_credit_limit)->toBe(0.0);
});

test('a future agreement is approved and activated by the daily process on its start date', function (): void {
    $startsOn = $this->today->addDays(5);
    $agreement = activeAgreement($this->workflow, $this->admin, agreementData($this->partner, [
        'starts_on' => $startsOn->toDateString(),
        'ends_on' => $startsOn->addYear()->toDateString(),
    ]));

    expect($agreement->status)->toBe(PartnerAgreementStatus::Approved);

    app(PartnerAgreementLifecycleRunner::class)->run($startsOn->subDay());
    expect($agreement->fresh()->status)->toBe(PartnerAgreementStatus::Approved);

    app(PartnerAgreementLifecycleRunner::class)->run($startsOn);
    expect($agreement->fresh()->status)->toBe(PartnerAgreementStatus::Active);
});

test('business rules block inconsistent agreements', function (callable $makeInvalid, string $message): void {
    activeAgreement($this->workflow, $this->admin, agreementData($this->partner));

    expect(fn () => $makeInvalid($this->workflow, $this->admin, $this->partner))
        ->toThrow(PartnerAgreementException::class, $message);
})->with([
    'second initial' => [
        fn ($workflow, $admin, $partner) => $workflow->createDraft(agreementData($partner), $admin),
        'ya tiene un convenio vigente',
    ],
    'renewal overlapping the current term' => [
        fn ($workflow, $admin, $partner) => $workflow->createDraft(agreementData($partner, [
            'type' => PartnerAgreementType::Renewal->value,
            'change_reason' => 'Renovación',
            'starts_on' => CarbonImmutable::today()->addMonth()->toDateString(),
        ]), $admin),
        'debe iniciar después del fin',
    ],
    'amendment without reason' => [
        fn ($workflow, $admin, $partner) => $workflow->createDraft(agreementData($partner, [
            'type' => PartnerAgreementType::Amendment->value,
        ]), $admin),
        'Indique el motivo',
    ],
    'credit without limit' => [
        fn ($workflow, $admin, $partner) => $workflow->createDraft(agreementData($partner, [
            'type' => PartnerAgreementType::Amendment->value,
            'change_reason' => 'x',
            'credit_limit_usd' => null,
        ]), $admin),
        'requiere un límite de crédito',
    ],
]);

test('only one agreement can be in progress per partner', function (): void {
    activeAgreement($this->workflow, $this->admin, agreementData($this->partner));

    $amendment = agreementData($this->partner, [
        'type' => PartnerAgreementType::Amendment->value,
        'change_reason' => 'Ajuste',
    ]);

    $this->workflow->createDraft($amendment, $this->admin);

    expect(fn () => $this->workflow->createDraft($amendment, $this->admin))
        ->toThrow(PartnerAgreementException::class, 'en trámite');
});

test('an agreement cannot be submitted without the signed contract', function (): void {
    $agreement = $this->workflow->createDraft(agreementData($this->partner, ['signed_document_path' => null]), $this->admin);

    expect(fn () => $this->workflow->submit($agreement, $this->admin))
        ->toThrow(PartnerAgreementException::class, 'contrato firmado');
});

test('only administrators approve, reject or cancel', function (): void {
    $manager = User::factory()->create(['roles' => ['GERENCIA']]);
    $agreement = $this->workflow->createDraft(agreementData($this->partner), $this->admin);
    $this->workflow->submit($agreement, $this->admin);

    expect(fn () => $this->workflow->approve($agreement->fresh(), $manager))
        ->toThrow(PartnerAgreementException::class, 'Solo un Administrador')
        ->and(fn () => $this->workflow->reject($agreement->fresh(), $manager, 'No'))
        ->toThrow(PartnerAgreementException::class, 'Solo un Administrador');
});

test('a rejected agreement goes back to draft when edited and can be resubmitted', function (): void {
    $agreement = $this->workflow->createDraft(agreementData($this->partner), $this->admin);
    $this->workflow->submit($agreement, $this->admin);
    $rejected = $this->workflow->reject($agreement->fresh(), $this->admin, 'Falta firma del representante');

    expect($rejected->status)->toBe(PartnerAgreementStatus::Rejected);

    $edited = $this->workflow->updateDraft($rejected, ['discount_percentage' => 7], $this->admin);
    expect($edited->status)->toBe(PartnerAgreementStatus::Draft);

    expect($this->workflow->submit($edited, $this->admin)->status)->toBe(PartnerAgreementStatus::PendingApproval);
});

test('an active agreement cannot be edited', function (): void {
    $agreement = activeAgreement($this->workflow, $this->admin, agreementData($this->partner));

    expect(fn () => $this->workflow->updateDraft($agreement, ['discount_percentage' => 1], $this->admin))
        ->toThrow(PartnerAgreementException::class, 'Solo se pueden editar');
});

test('the daily process renews auto-renewing agreements with the same conditions', function (): void {
    $agreement = activeAgreement($this->workflow, $this->admin, agreementData($this->partner, [
        'auto_renews' => true,
        'renewal_term_months' => 6,
        'ends_on' => $this->today->addMonths(3)->toDateString(),
    ]));

    $dayAfterEnd = $agreement->ends_on->addDay();
    $summary = app(PartnerAgreementLifecycleRunner::class)->run($dayAfterEnd);

    $renewal = PartnerCompanyAgreement::query()->where('previous_agreement_id', $agreement->id)->firstOrFail();

    expect($summary['renewed'])->toBe(1)
        ->and($agreement->fresh()->status)->toBe(PartnerAgreementStatus::Expired)
        ->and($renewal->status)->toBe(PartnerAgreementStatus::Active)
        ->and($renewal->is_automatic)->toBeTrue()
        ->and($renewal->starts_on->toDateString())->toBe($dayAfterEnd->toDateString())
        ->and($renewal->ends_on->toDateString())->toBe($dayAfterEnd->addMonthsNoOverflow(6)->subDay()->toDateString())
        ->and((float) $renewal->credit_limit_usd)->toBe(1000.0)
        ->and($renewal->signed_document_path)->toBe($agreement->signed_document_path);

    expect(app(PartnerAgreementLifecycleRunner::class)->run($dayAfterEnd)['renewed'])->toBe(0);
});

test('agreements without auto renewal expire and notify the involved users', function (): void {
    $agreement = activeAgreement($this->workflow, $this->admin, agreementData($this->partner, [
        'ends_on' => $this->today->addDays(3)->toDateString(),
    ]));

    Queue::fake();

    $summary = app(PartnerAgreementLifecycleRunner::class)->run($agreement->ends_on->addDay());

    expect($summary['expired'])->toBe(1)
        ->and($agreement->fresh()->status)->toBe(PartnerAgreementStatus::Expired);

    Queue::assertPushed(NotifyPartnerAgreementJob::class, fn (NotifyPartnerAgreementJob $job): bool => $job->event === PartnerAgreementEventType::Expired->value
        && $job->userId === $this->admin->id
        && $job->channel === NotifyPartnerAgreementJob::CHANNEL_MAIL);
    Queue::assertPushed(NotifyPartnerAgreementJob::class, fn (NotifyPartnerAgreementJob $job): bool => $job->event === PartnerAgreementEventType::Expired->value
        && $job->channel === NotifyPartnerAgreementJob::CHANNEL_WHATSAPP);
});

test('a pending renewal takes priority over the automatic renewal', function (): void {
    $agreement = activeAgreement($this->workflow, $this->admin, agreementData($this->partner, [
        'auto_renews' => true,
        'ends_on' => $this->today->addDays(3)->toDateString(),
    ]));

    $this->workflow->createDraft(agreementData($this->partner, [
        'type' => PartnerAgreementType::Renewal->value,
        'change_reason' => 'Renovación con nuevas condiciones',
        'starts_on' => $agreement->ends_on->addDay()->toDateString(),
        'ends_on' => $agreement->ends_on->addYear()->toDateString(),
    ]), $this->admin);

    $summary = app(PartnerAgreementLifecycleRunner::class)->run($agreement->ends_on->addDay());

    expect($summary['renewed'])->toBe(0)
        ->and($summary['expired'])->toBe(1)
        ->and(PartnerCompanyAgreement::query()->where('is_automatic', true)->exists())->toBeFalse();
});

test('expiry notices are sent once per threshold', function (): void {
    $agreement = activeAgreement($this->workflow, $this->admin, agreementData($this->partner, [
        'ends_on' => $this->today->addDays(40)->toDateString(),
    ]));

    $runner = app(PartnerAgreementLifecycleRunner::class);

    expect($runner->run($this->today)['notices'])->toBe(1)
        ->and($runner->run($this->today)['notices'])->toBe(0)
        ->and($agreement->notices()->pluck('threshold_days')->all())->toBe([60]);

    expect($runner->run($this->today->addDays(25))['notices'])->toBe(1)
        ->and($agreement->notices()->orderBy('threshold_days')->pluck('threshold_days')->all())->toBe([15, 30, 60]);
});
