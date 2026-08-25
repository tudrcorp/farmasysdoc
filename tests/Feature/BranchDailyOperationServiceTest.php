<?php

use App\Models\Branch;
use App\Models\BranchDailyOperation;
use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Services\Branches\BranchDailyOperationService;
use App\Support\Branches\BranchDailyOperationException;
use App\Support\Branches\BranchDailyOperationRecipients;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['services.ultramsg.enabled' => false]);
});

function branchDailyAdmin(): User
{
    return User::factory()->create([
        'roles' => ['ADMINISTRADOR'],
        'whatsapp_phone' => '04141234567',
    ]);
}

function branchDailyManager(Branch $branch): User
{
    return User::factory()->create([
        'branch_id' => $branch->id,
        'roles' => ['GERENTE'],
        'whatsapp_phone' => '04149876543',
    ]);
}

function branchDailyCashier(Branch $branch): User
{
    return User::factory()->create([
        'branch_id' => $branch->id,
        'roles' => ['CAJERO'],
    ]);
}

it('permite a un gerente aperturar la sucursal y registra el log de auditoria', function (): void {
    $branch = Branch::factory()->create();
    $manager = branchDailyManager($branch);

    $operation = app(BranchDailyOperationService::class)->open($manager, $branch);

    expect($operation->isOpen())->toBeTrue()
        ->and((int) $operation->branch_id)->toBe((int) $branch->id)
        ->and((int) $operation->opened_by_user_id)->toBe((int) $manager->id);

    expect(DB::table('audit_logs')->where('event', 'branch_daily_operation_opened')->exists())->toBeTrue();
});

it('rechaza que un cajero apertura o cierre la sucursal', function (): void {
    $branch = Branch::factory()->create();
    $cashier = branchDailyCashier($branch);
    $service = app(BranchDailyOperationService::class);

    expect(fn () => $service->open($cashier, $branch))
        ->toThrow(BranchDailyOperationException::class);

    $service->open(branchDailyAdmin(), $branch);

    expect(fn () => $service->close($cashier, $branch->fresh()))
        ->toThrow(BranchDailyOperationException::class);
});

it('impide al gerente reaperturar el mismo dia y permite al administrador', function (): void {
    $branch = Branch::factory()->create();
    $manager = branchDailyManager($branch);
    $admin = branchDailyAdmin();
    $service = app(BranchDailyOperationService::class);

    $service->open($manager, $branch);
    $service->close($manager, $branch->fresh());

    expect($service->canOpen($manager, $branch->fresh()))->toBeFalse();

    expect(fn () => $service->open($manager, $branch->fresh()))
        ->toThrow(BranchDailyOperationException::class);

    $reopened = $service->open($admin, $branch->fresh());

    expect($reopened->isOpen())->toBeTrue();
});

it('no cierra la sucursal si hay cajas fisicas abiertas y indica cuales son', function (): void {
    $branch = Branch::factory()->create();
    $manager = branchDailyManager($branch);
    $cashier = branchDailyCashier($branch);
    $service = app(BranchDailyOperationService::class);

    $service->open($manager, $branch);

    PhysicalCashBox::factory()->open()->create([
        'user_id' => $cashier->id,
    ]);

    try {
        $service->close($manager, $branch->fresh());
        $this->fail('Se esperaba una excepcion por cajas abiertas.');
    } catch (BranchDailyOperationException $exception) {
        expect($exception->openCashBoxLabels)->toContain('Caja de '.$cashier->name)
            ->and($exception->getMessage())->toContain($cashier->name);
    }

    expect($service->isOpen($branch->fresh()))->toBeTrue();
});

it('cierra la sucursal cuando todas las cajas estan cerradas y registra auditoria', function (): void {
    $branch = Branch::factory()->create();
    $manager = branchDailyManager($branch);
    $cashier = branchDailyCashier($branch);
    $service = app(BranchDailyOperationService::class);

    $service->open($manager, $branch);

    PhysicalCashBox::factory()->create([
        'user_id' => $cashier->id,
        'is_open' => false,
    ]);

    $closed = $service->close($manager, $branch->fresh());

    expect($closed->isOpen())->toBeFalse()
        ->and((int) $closed->closed_by_user_id)->toBe((int) $manager->id);

    expect(DB::table('audit_logs')->where('event', 'branch_daily_operation_closed')->exists())->toBeTrue();
});

it('notifica al gerente de varias sucursales solo por las asociadas a el', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $unrelated = Branch::factory()->create();

    $gerencia = User::factory()->create([
        'roles' => ['GERENCIA'],
        'branch_id' => $ownBranch->id,
        'whatsapp_phone' => '04141112233',
    ]);
    $gerencia->managedBranches()->sync([$ownBranch->id, $otherBranch->id]);

    $recipients = app(BranchDailyOperationRecipients::class);

    expect($recipients->shouldNotifyUser($gerencia->fresh(), (int) $ownBranch->id))->toBeTrue()
        ->and($recipients->shouldNotifyUser($gerencia->fresh(), (int) $otherBranch->id))->toBeTrue()
        ->and($recipients->shouldNotifyUser($gerencia->fresh(), (int) $unrelated->id))->toBeFalse();
});

it('no permite duplicar una gestion abierta', function (): void {
    $branch = Branch::factory()->create();
    $admin = branchDailyAdmin();
    $service = app(BranchDailyOperationService::class);

    $service->open($admin, $branch);

    expect(fn () => $service->open($admin, $branch->fresh()))
        ->toThrow(BranchDailyOperationException::class);

    expect(
        BranchDailyOperation::query()->where('branch_id', $branch->id)->whereNull('closed_at')->count()
    )->toBe(1);
});
