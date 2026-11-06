<?php

use App\Enums\SupplierReturnEventType;
use App\Enums\SupplierReturnStatus;
use App\Filament\Resources\SupplierReturns\Pages\CreateSupplierReturn;
use App\Filament\Resources\SupplierReturns\Pages\ViewSupplierReturn;
use App\Filament\Widgets\SupplierReturnsPendingWidget;
use App\Jobs\NotifySupplierReturnJob;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Rol;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\Purchases\SupplierReturnException;
use App\Services\Purchases\SupplierReturnWorkflow;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
    Queue::fake();
    Notification::fake();

    Rol::query()->create(['name' => 'GERENCIA', 'is_active' => true, 'allowed_menu_items' => ['supplier_returns', 'purchases']]);

    $this->branch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $this->manager = User::factory()->create(['roles' => ['GERENCIA'], 'branch_id' => $this->branch->id, 'whatsapp_phone' => '584141112233']);
    $this->workflow = app(SupplierReturnWorkflow::class);
});

/**
 * @param  list<array<string, mixed>>|null  $items
 */
function registerReturn(object $test, ?array $items = null, ?int $branchId = null): SupplierReturn
{
    return $test->workflow->register([
        'branch_id' => $branchId ?? $test->branch->id,
        'supplier_id' => $test->supplier->id,
        'received_on' => CarbonImmutable::today()->toDateString(),
        'notes' => 'Llegaron cajas de más',
    ], $items ?? [
        ['barcode' => '7590000000001', 'product_name' => 'Acetaminofén 500 mg', 'quantity_invoiced' => 10, 'quantity_received' => 13],
    ], [], $test->manager);
}

test('a manager registers excess merchandise and administrators are notified', function (): void {
    $return = registerReturn($this);

    expect($return->status)->toBe(SupplierReturnStatus::PendingReview)
        ->and($return->number)->toStartWith('DEV-')
        ->and((float) $return->items()->first()->quantity_excess)->toBe(3.0)
        ->and($return->events()->first()->type)->toBe(SupplierReturnEventType::Created);

    Queue::assertPushed(NotifySupplierReturnJob::class, fn (NotifySupplierReturnJob $job): bool => $job->userId === $this->admin->id
        && $job->event === SupplierReturnEventType::Created->value);
});

test('the received quantity must exceed the invoiced quantity', function (): void {
    expect(fn () => registerReturn($this, [
        ['product_name' => 'Ibuprofeno', 'quantity_invoiced' => 5, 'quantity_received' => 5],
    ]))->toThrow(SupplierReturnException::class, 'debe ser mayor que la facturada');
});

test('a manager cannot register returns for a branch outside their scope', function (): void {
    expect(fn () => registerReturn($this, branchId: $this->otherBranch->id))
        ->toThrow(SupplierReturnException::class, 'No tiene acceso a la sucursal');
});

test('the product is resolved from the catalog by barcode ignoring spaces', function (): void {
    $product = Product::factory()->create(['barcode' => ' 7591111111111', 'name' => 'Loratadina 10 mg']);

    $return = registerReturn($this, [
        ['barcode' => '7591111111111', 'product_name' => '', 'quantity_invoiced' => 0, 'quantity_received' => 2],
    ]);

    $item = $return->items()->first();

    expect($item->product_id)->toBe($product->id)
        ->and($item->product_name)->toBe('Loratadina 10 mg');
});

test('only administrators approve or reject', function (): void {
    $return = registerReturn($this);

    expect(fn () => $this->workflow->approve($return, $this->manager))
        ->toThrow(SupplierReturnException::class, 'Solo un Administrador');

    $approved = $this->workflow->approve($return, $this->admin, 'Coordinar retiro');

    expect($approved->status)->toBe(SupplierReturnStatus::Approved);
    Queue::assertPushed(NotifySupplierReturnJob::class, fn (NotifySupplierReturnJob $job): bool => $job->userId === $this->manager->id
        && $job->event === SupplierReturnEventType::Approved->value);
});

test('a rejected return keeps the reason and cannot be returned', function (): void {
    $return = $this->workflow->reject(registerReturn($this), $this->admin, 'Era parte del pedido');

    expect($return->status)->toBe(SupplierReturnStatus::Rejected)
        ->and($return->rejection_reason)->toBe('Era parte del pedido')
        ->and(fn () => $this->workflow->markReturned($return, $this->manager, CarbonImmutable::today(), null))
        ->toThrow(SupplierReturnException::class, 'Solo se marcan como devueltas');
});

test('the branch manager marks an approved return as delivered to the supplier', function (): void {
    $return = $this->workflow->approve(registerReturn($this), $this->admin);

    expect(fn () => $this->workflow->markReturned($return, $this->manager, CarbonImmutable::tomorrow(), null))
        ->toThrow(SupplierReturnException::class, 'no puede ser futura');

    $returned = $this->workflow->markReturned($return, $this->manager, CarbonImmutable::today(), 'Recibió el chofer');

    expect($returned->status)->toBe(SupplierReturnStatus::Returned)
        ->and($returned->returned_by)->toBe($this->manager->id)
        ->and($returned->return_notes)->toBe('Recibió el chofer');
});

test('only pending returns can be edited', function (): void {
    $return = $this->workflow->approve(registerReturn($this), $this->admin);

    expect(fn () => $this->workflow->update($return, ['notes' => 'x'], [
        ['product_name' => 'A', 'quantity_invoiced' => 1, 'quantity_received' => 2],
    ], [], $this->manager))->toThrow(SupplierReturnException::class, 'Solo se editan');
});

test('the create page registers a return', function (): void {
    Livewire::actingAs($this->manager)
        ->test(CreateSupplierReturn::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'received_on' => now()->toDateString(),
            'notes' => 'Llegó una caja de más',
            'items' => [
                ['barcode' => '7592222222222', 'product_name' => 'Vitamina C', 'quantity_invoiced' => 12, 'quantity_received' => 24],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SupplierReturn::query()->where('branch_id', $this->branch->id)->first()?->totalExcess())->toBe(12.0);
});

test('the admin dashboard alert lists pending returns', function (): void {
    $return = registerReturn($this);

    $this->actingAs($this->admin);
    expect(SupplierReturnsPendingWidget::canView())->toBeTrue();

    Livewire::test(SupplierReturnsPendingWidget::class)
        ->assertCanSeeTableRecords([$return]);

    $this->actingAs($this->manager);
    expect(SupplierReturnsPendingWidget::canView())->toBeFalse();
});

test('managers do not see the approve action', function (): void {
    $return = registerReturn($this);

    Livewire::actingAs($this->manager)
        ->test(ViewSupplierReturn::class, ['record' => $return->getKey()])
        ->assertActionHidden('approve');

    Livewire::actingAs($this->admin)
        ->test(ViewSupplierReturn::class, ['record' => $return->getKey()])
        ->assertActionVisible('approve');
});
