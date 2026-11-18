<?php

use App\Enums\OrderStatus;
use App\Enums\PartnerAffiliateSource;
use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Pages\Auth\Login;
use App\Filament\BusinessPartners\Pages\Auth\RequestPasswordReset;
use App\Filament\BusinessPartners\Resources\TeamUsers\Pages\ManageTeamUsers;
use App\Jobs\SendPartnerPanelAccessJob;
use App\Mail\PartnerPanelAccessMail;
use App\Models\Client;
use App\Models\Order;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyPlan;
use App\Models\PartnerCompanyUser;
use App\Models\PartnerPanelActivity;
use App\Models\Plan;
use App\Models\User;
use App\Services\Partners\PartnerAffiliateService;
use App\Services\Partners\PartnerDashboardMetrics;
use App\Services\Partners\PartnerPanelUserService;
use App\Support\PartnerCompanyAlliedUsersFormSync;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(User::PARTNER_PANEL_ID);
    $this->partner = PartnerCompany::factory()->create(['legal_name' => 'Aliado Uno C.A.']);
});

describe('panel access', function (): void {
    test('a panel administrator opens every section of the panel', function (string $path): void {
        $user = User::factory()->partnerPanelUser($this->partner, admin: true)->create();

        $this->actingAs($user)->get($path)->assertOk();
    })->with([
        'inicio' => '/business-partners',
        'planes y convenios' => '/business-partners/planes-y-convenios',
        'mi empresa' => '/business-partners/mi-empresa',
        'pedidos' => '/business-partners/orders',
        'crear pedido' => '/business-partners/orders/create',
        'usuarios' => '/business-partners/usuarios',
        'guía' => '/business-partners/guia-del-modulo',
    ]);

    test('sections follow the user permissions', function (): void {
        $user = User::factory()->partnerPanelUser($this->partner, [PartnerPanelPermission::ViewOrders])->create();

        $this->actingAs($user);
        $this->get('/business-partners')->assertOk()->assertDontSee('Pedidos del mes');
        $this->get('/business-partners/orders')->assertOk();
        $this->get('/business-partners/orders/create')->assertForbidden();
        $this->get('/business-partners/usuarios')->assertForbidden();
        $this->get('/business-partners/planes-y-convenios')->assertForbidden();
    });

    test('Farmadoc staff cannot enter the partner panel and partner users cannot enter farmaadmin', function (): void {
        $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
        $partnerUser = User::factory()->partnerPanelUser($this->partner)->create();

        $this->actingAs($admin)->get('/business-partners')->assertForbidden();

        expect($partnerUser->canAccessPanel(Filament::getPanel('farmaadmin')))->toBeFalse()
            ->and($partnerUser->canAccessPanel(Filament::getPanel(User::PARTNER_PANEL_ID)))->toBeTrue();
    });

    test('suspended users and users of an inactive company cannot enter', function (): void {
        $suspended = User::factory()->partnerPanelUser($this->partner)->create(['partner_user_is_active' => false]);
        $active = User::factory()->partnerPanelUser($this->partner)->create();

        expect($suspended->canAccessPartnerPanel())->toBeFalse();

        $this->partner->update(['is_active' => false]);

        expect($active->fresh()->canAccessPartnerPanel())->toBeFalse()
            ->and($active->fresh()->partnerPanelAccessDeniedReason())->toContain('no está activa');
    });

    test('a partner only sees the orders and users of its own company', function (): void {
        $other = PartnerCompany::factory()->create();
        $user = User::factory()->partnerPanelUser($this->partner, admin: true)->create();
        $foreignUser = User::factory()->partnerPanelUser($other)->create();
        $foreignOrder = Order::factory()->create(['partner_company_id' => $other->getKey(), 'client_id' => null]);

        $this->actingAs($user);
        $this->get("/business-partners/orders/{$foreignOrder->getKey()}")->assertNotFound();
        $this->get('/business-partners/usuarios')->assertOk()->assertDontSee($foreignUser->email);
    });
});

describe('login', function (): void {
    test('a suspended user with the right password sees why they cannot enter', function (): void {
        User::factory()->partnerPanelUser($this->partner)->create([
            'email' => 'ana@aliado.test',
            'password' => Hash::make('Secreta123!'),
            'partner_user_is_active' => false,
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'ana@aliado.test', 'password' => 'Secreta123!'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    });

    test('a user who has not created a password yet is told to use the email link', function (): void {
        User::factory()->partnerPanelUser($this->partner)->create(['email' => 'nuevo@aliado.test', 'partner_activated_at' => null]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'nuevo@aliado.test', 'password' => '12345678'])
            ->call('authenticate')
            ->assertHasFormErrors(['email'])
            ->assertSee('todavía no tiene clave');
    });

    test('an active partner user signs in', function (): void {
        $user = User::factory()->partnerPanelUser($this->partner)->create([
            'email' => 'ana@aliado.test',
            'password' => Hash::make('Secreta123!'),
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'ana@aliado.test', 'password' => 'Secreta123!'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
        expect($user->fresh()->partner_last_login_at)->not->toBeNull();
    });

    test('failed attempts are limited per account', function (): void {
        User::factory()->partnerPanelUser($this->partner)->create(['email' => 'ana@aliado.test']);

        $component = Livewire::test(Login::class);

        foreach (range(1, Login::MAX_ATTEMPTS_PER_ACCOUNT) as $attempt) {
            $component->fillForm(['email' => 'ana@aliado.test', 'password' => 'mala-'.$attempt])->call('authenticate');
        }

        $component->fillForm(['email' => 'ana@aliado.test', 'password' => 'mala-otra'])
            ->call('authenticate')
            ->assertSee('Demasiados intentos');
    });
});

describe('access links', function (): void {
    test('creating a user generates an unknown password, links it and sends the invitation', function (): void {
        Queue::fake();

        $user = app(PartnerPanelUserService::class)->create($this->partner, [
            'name' => 'Ana Pérez',
            'email' => '  ANA@Aliado.TEST ',
            'permissions' => [PartnerPanelPermission::CreateOrders->value],
        ]);

        expect($user->email)->toBe('ana@aliado.test')
            ->and($user->partnerAccessStatus()['key'])->toBe('pending')
            ->and($user->partnerPermissionValues())->toBe([PartnerPanelPermission::ViewOrders->value, PartnerPanelPermission::CreateOrders->value])
            ->and(PartnerCompanyUser::query()->where('user_id', $user->getKey())->where('partner_company_id', $this->partner->getKey())->exists())->toBeTrue()
            ->and($user->partner_access_sent_at)->not->toBeNull();

        Queue::assertPushed(SendPartnerPanelAccessJob::class, fn (SendPartnerPanelAccessJob $job): bool => $job->userId === $user->getKey() && $job->isInvitation);
    });

    test('a registered email cannot be reused', function (): void {
        User::factory()->create(['email' => 'ana@aliado.test']);

        app(PartnerPanelUserService::class)->create($this->partner, ['name' => 'Ana', 'email' => 'ana@aliado.test']);
    })->throws(ValidationException::class);

    test('the access email carries a reset link of the partner panel', function (): void {
        Mail::fake();
        $user = User::factory()->partnerPanelUser($this->partner)->create(['partner_activated_at' => null]);

        (new SendPartnerPanelAccessJob($user->getKey(), true))->handle();

        Mail::assertSent(PartnerPanelAccessMail::class, fn (PartnerPanelAccessMail $mail): bool => $mail->hasTo($user->email)
            && $mail->isInvitation
            && str_contains($mail->actionUrl, '/business-partners/password-reset/reset'));
    });

    test('access cannot be resent within a minute or to a suspended user', function (): void {
        Queue::fake();
        $service = app(PartnerPanelUserService::class);
        $user = User::factory()->partnerPanelUser($this->partner)->create();

        $service->sendAccess($user);

        expect(fn () => $service->sendAccess($user->fresh()))->toThrow(ValidationException::class);

        $this->travel(2)->minutes();
        $user->fresh()->update(['partner_user_is_active' => false]);

        expect(fn () => $service->sendAccess($user->fresh()))->toThrow(ValidationException::class);
        Queue::assertPushed(SendPartnerPanelAccessJob::class, 1);
    });

    test('only administrators can generate an access link without email, and it is audited', function (): void {
        $user = User::factory()->partnerPanelUser($this->partner)->create(['partner_activated_at' => null]);
        $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
        $manager = User::factory()->create(['roles' => ['GERENCIA']]);
        $service = app(PartnerPanelUserService::class);

        $url = $service->accessLink($user, $admin);

        expect($url)->toContain('/business-partners/password-reset/reset')
            ->and(DB::table('audit_logs')->where('event', 'partner_access_link_generated')->count())->toBe(1)
            ->and(fn () => $service->accessLink($user, $manager))->toThrow(ValidationException::class);
    });

    test('creating the password with the link activates the user', function (): void {
        $user = User::factory()->partnerPanelUser($this->partner)->create(['partner_activated_at' => null, 'email_verified_at' => null]);

        event(new PasswordReset($user));

        expect($user->fresh()->partnerAccessStatus()['key'])->toBe('active')
            ->and($user->fresh()->email_verified_at)->not->toBeNull();
    });

    test('the forgot password page answers the same and only emails active partner users', function (): void {
        Queue::fake();
        $user = User::factory()->partnerPanelUser($this->partner)->create(['email' => 'ana@aliado.test']);
        User::factory()->create(['email' => 'staff@farmadoc.test', 'roles' => ['ADMINISTRADOR']]);

        foreach (['ana@aliado.test', 'staff@farmadoc.test', 'nadie@aliado.test'] as $email) {
            Livewire::test(RequestPasswordReset::class)
                ->fillForm(['email' => $email])
                ->call('request')
                ->assertNotified('Revise su correo');
        }

        Queue::assertPushed(SendPartnerPanelAccessJob::class, 1);
        Queue::assertPushed(SendPartnerPanelAccessJob::class, fn (SendPartnerPanelAccessJob $job): bool => $job->userId === $user->getKey());
    });
});

describe('standard password', function (): void {
    test('users created by Farmadoc staff start with the standard password and can sign in right away', function (): void {
        Queue::fake();
        $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
        $this->actingAs($admin);

        PartnerCompanyAlliedUsersFormSync::createUsers($this->partner, [
            ['name' => 'Ana Pérez', 'email' => 'ana@aliado.test', 'permissions' => [PartnerPanelPermission::Dashboard->value], 'is_active' => true],
        ]);

        $user = User::query()->where('email', 'ana@aliado.test')->firstOrFail();

        expect(Hash::check(PartnerPanelUserService::defaultPassword(), $user->password))->toBeTrue()
            ->and($user->usesDefaultPartnerPassword())->toBeTrue()
            ->and($user->partnerAccessStatus()['key'])->toBe('active');
        Queue::assertNothingPushed();
    });

    test('the standard password flag turns off when the user changes the password', function (): void {
        $user = User::factory()->partnerPanelUser($this->partner)->create(['password' => PartnerPanelUserService::defaultPassword()]);

        expect($user->fresh()->usesDefaultPartnerPassword())->toBeTrue();

        $this->actingAs($user)->get('/business-partners')->assertOk()->assertSee('Está usando la clave estándar');

        $user->update(['password' => 'OtraClave2026!']);

        expect($user->fresh()->usesDefaultPartnerPassword())->toBeFalse();
    });
});

describe('team management', function (): void {
    test('the panel administrator creates a teammate who receives the invitation', function (): void {
        Queue::fake();
        $manager = User::factory()->partnerPanelUser($this->partner, admin: true)->create();

        Livewire::actingAs($manager)
            ->test(ManageTeamUsers::class)
            ->callAction(CreateAction::class, [
                'name' => 'Luis Gómez',
                'email' => 'luis@aliado.test',
                'partner_permissions' => [PartnerPanelPermission::ViewOrders->value],
            ])
            ->assertHasNoActionErrors();

        $created = User::query()->where('email', 'luis@aliado.test')->firstOrFail();

        expect((int) $created->partner_company_id)->toBe((int) $this->partner->getKey())
            ->and($created->partnerPermissionValues())->toBe([PartnerPanelPermission::ViewOrders->value]);
        Queue::assertPushed(SendPartnerPanelAccessJob::class);
    });

    test('a panel administrator cannot change itself nor another administrator, and analysts cannot manage users', function (): void {
        $admin = User::factory()->partnerPanelUser($this->partner, admin: true)->create();
        $otherAdmin = User::factory()->partnerPanelUser($this->partner, admin: true)->create();
        $analyst = User::factory()->partnerPanelUser($this->partner)->create();
        $service = app(PartnerPanelUserService::class);

        expect(fn () => $service->suspend($admin, $admin))->toThrow(ValidationException::class)
            ->and(fn () => $service->update($otherAdmin, ['permissions' => []], $admin))->toThrow(ValidationException::class)
            ->and(fn () => $service->update($admin, ['permissions' => []], $analyst))->toThrow(ValidationException::class)
            ->and(fn () => $service->create($this->partner, ['name' => 'X', 'email' => 'x@aliado.test'], actor: $analyst))->toThrow(ValidationException::class);

        $this->actingAs($analyst)->get('/business-partners/usuarios')->assertForbidden();
    });

    test('only Farmadoc designates administrators and every change lands in the activity log', function (): void {
        $admin = User::factory()->partnerPanelUser($this->partner, admin: true)->create();
        $staff = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
        $service = app(PartnerPanelUserService::class);

        $created = $service->create($this->partner, ['name' => 'Luis', 'email' => 'luis@aliado.test', 'is_admin' => true], sendAccess: false, actor: $admin);
        expect($created->partner_is_admin)->toBeFalse();

        $service->update($created, ['permissions' => [PartnerPanelPermission::DeactivateAffiliates->value]], $admin);
        $service->update($created, ['is_admin' => true], $staff);

        expect($created->fresh()->isPartnerAdmin())->toBeTrue()
            ->and(PartnerPanelActivity::query()->where('partner_company_id', $this->partner->getKey())->pluck('action')->all())
            ->toContain('user_created', 'permissions_changed', 'admin_granted');

        $this->actingAs($admin)->get('/business-partners/bitacora')->assertOk()->assertSee('Luis');
    });

    test('affiliates are deactivated only with the permission and a reason', function (): void {
        $assignment = PartnerCompanyPlan::factory()->withConsumptionFund(100.0)->create([
            'partner_company_id' => $this->partner->getKey(),
            'plan_id' => Plan::factory()->consumptionFund()->active(),
        ]);
        $affiliate = app(PartnerAffiliateService::class)->enroll($assignment, Client::factory()->create(), PartnerAffiliateSource::Import);
        $analyst = User::factory()->partnerPanelUser($this->partner, [PartnerPanelPermission::PlansAndAgreements])->create();
        $allowed = User::factory()->partnerPanelUser($this->partner, [PartnerPanelPermission::PlansAndAgreements, PartnerPanelPermission::DeactivateAffiliates])->create();
        $service = app(PartnerAffiliateService::class);

        expect(fn () => $service->setActive($affiliate, false, $analyst, 'Egresó'))->toThrow(ValidationException::class)
            ->and(fn () => $service->setActive($affiliate, false, $allowed, ''))->toThrow(ValidationException::class);

        $service->setActive($affiliate, false, $allowed, 'Egresó de la empresa');

        expect($affiliate->fresh())
            ->is_active->toBeFalse()
            ->deactivated_via->toBe('aliado')
            ->deactivation_reason->toBe('Egresó de la empresa')
            ->and(PartnerPanelActivity::query()->where('action', 'affiliate_deactivated')->exists())->toBeTrue();
    });
});

describe('dashboard metrics', function (): void {
    test('metrics only count the orders of the partner and refresh when an order changes', function (): void {
        $other = PartnerCompany::factory()->create();
        Order::factory()->count(2)->create(['partner_company_id' => $this->partner->getKey(), 'client_id' => null, 'total' => 100, 'status' => OrderStatus::Completed]);
        Order::factory()->create(['partner_company_id' => $other->getKey(), 'client_id' => null, 'total' => 999]);

        $metrics = app(PartnerDashboardMetrics::class)->for($this->partner->getKey());

        expect($metrics['month']['orders'])->toBe(2)
            ->and($metrics['month']['amount'])->toBe(200.0)
            ->and(collect($metrics['statuses'])->firstWhere('status', OrderStatus::Completed->value)['count'])->toBe(2)
            ->and(end($metrics['series']['orders']))->toBe(2)
            ->and(Cache::has(PartnerDashboardMetrics::cacheKey($this->partner->getKey())))->toBeTrue();

        Order::factory()->create(['partner_company_id' => $this->partner->getKey(), 'client_id' => null, 'total' => 50]);

        expect(Cache::has(PartnerDashboardMetrics::cacheKey($this->partner->getKey())))->toBeFalse()
            ->and(app(PartnerDashboardMetrics::class)->for($this->partner->getKey())['month']['orders'])->toBe(3);
    });
});

test('permissions are normalized: creating orders implies seeing them and unknown values are dropped', function (): void {
    expect(PartnerPanelPermission::normalize(['orders_create', 'nope', 'dashboard', 'dashboard']))
        ->toBe([PartnerPanelPermission::Dashboard->value, PartnerPanelPermission::ViewOrders->value, PartnerPanelPermission::CreateOrders->value]);
});
