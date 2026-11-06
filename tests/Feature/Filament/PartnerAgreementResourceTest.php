<?php

use App\Enums\PartnerAgreementStatus;
use App\Filament\Resources\PartnerAgreements\Pages\CreatePartnerCompanyAgreement;
use App\Filament\Resources\PartnerAgreements\Pages\ListPartnerCompanyAgreements;
use App\Filament\Resources\PartnerAgreements\Pages\ViewPartnerCompanyAgreement;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Models\Rol;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
    Storage::fake('local');
    Queue::fake();
    Notification::fake();
    $this->admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
});

test('the agreements list shows the expiring tab', function (): void {
    $expiring = PartnerCompanyAgreement::factory()->active()->create(['ends_on' => now()->addDays(10)->toDateString()]);
    $later = PartnerCompanyAgreement::factory()->active()->create(['ends_on' => now()->addDays(200)->toDateString()]);

    Livewire::actingAs($this->admin)
        ->test(ListPartnerCompanyAgreements::class)
        ->assertSuccessful()
        ->set('activeTab', 'expiring')
        ->assertCanSeeTableRecords([$expiring])
        ->assertCanNotSeeTableRecords([$later]);
});

test('creating an agreement from the panel saves a draft', function (): void {
    $partner = PartnerCompany::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(CreatePartnerCompanyAgreement::class)
        ->fillForm([
            'partner_company_id' => $partner->id,
            'type' => 'inicial',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'payment_modality' => 'contado',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $agreement = PartnerCompanyAgreement::query()->where('partner_company_id', $partner->id)->firstOrFail();

    expect($agreement->status)->toBe(PartnerAgreementStatus::Draft)
        ->and($agreement->created_by)->toBe($this->admin->id);
});

test('the create page prefills a renewal from the query string', function (): void {
    $current = PartnerCompanyAgreement::factory()->active()->onCredit(900)->create([
        'starts_on' => now()->subMonths(11)->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
    ]);

    Livewire::actingAs($this->admin)
        ->withQueryParams(['partner' => $current->partner_company_id, 'type' => 'renovacion'])
        ->test(CreatePartnerCompanyAgreement::class)
        ->assertFormSet([
            'partner_company_id' => $current->partner_company_id,
            'type' => 'renovacion',
            'starts_on' => $current->ends_on->addDay()->toDateString(),
            'credit_limit_usd' => '900.00',
        ]);
});

test('an administrator approves a pending agreement from the view page', function (): void {
    Storage::disk('local')->put('partner-companies/agreements/c.pdf', 'pdf');
    $agreement = PartnerCompanyAgreement::factory()->status(PartnerAgreementStatus::PendingApproval)->create([
        'signed_document_path' => 'partner-companies/agreements/c.pdf',
    ]);

    Livewire::actingAs($this->admin)
        ->test(ViewPartnerCompanyAgreement::class, ['record' => $agreement->getKey()])
        ->callAction('approve')
        ->assertNotified('Convenio aprobado');

    expect($agreement->fresh()->status)->toBe(PartnerAgreementStatus::Active);
});

test('managers do not see the approve action', function (): void {
    Rol::query()->create(['name' => 'GERENCIA', 'is_active' => true, 'allowed_menu_items' => ['partner_agreements']]);
    $manager = User::factory()->create(['roles' => ['GERENCIA']]);
    $agreement = PartnerCompanyAgreement::factory()->status(PartnerAgreementStatus::PendingApproval)->create();

    Livewire::actingAs($manager)
        ->test(ViewPartnerCompanyAgreement::class, ['record' => $agreement->getKey()])
        ->assertActionHidden('approve');
});
