<?php

use App\Enums\FiscalDocumentType;
use App\Filament\Resources\FiscalXReports\Pages\ListFiscalXReports;
use App\Filament\Resources\FiscalZReports\FiscalZReportResource;
use App\Filament\Resources\FiscalZReports\Pages\ListFiscalZReports;
use App\Filament\Resources\FiscalZReports\Pages\ViewFiscalZReport;
use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Models\FiscalZReport;
use App\Models\Rol;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
    $this->admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
});

test('the sales and billing group holds clients, sales book and fiscal reports', function (): void {
    $this->actingAs($this->admin);

    $group = collect(Filament::getNavigation())->first(fn ($group): bool => $group->getLabel() === 'Ventas y Facturación');

    expect($group)->not->toBeNull()
        ->and(collect($group->getItems())->map->getLabel()->values()->all())
        ->toBe(['Clientes', 'Libro de Ventas', 'Reportes X', 'Reportes Z']);
});

test('the Z reports list shows reports and is read only', function (): void {
    $report = FiscalZReport::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ListFiscalZReports::class)
        ->assertCanSeeTableRecords([$report]);

    Livewire::actingAs($this->admin)
        ->test(ViewFiscalZReport::class, ['record' => $report->getKey()])
        ->assertSuccessful();

    expect(FiscalZReportResource::canCreate())->toBeFalse()
        ->and(FiscalZReportResource::canEdit($report))->toBeFalse()
        ->and(FiscalZReportResource::canDelete($report))->toBeFalse();
});

test('users only see Z reports of their branches', function (): void {
    Rol::query()->create(['name' => 'GERENCIA', 'is_active' => true, 'allowed_menu_items' => ['fiscal_z_reports']]);
    [$own, $other] = Branch::factory()->count(2)->create()->all();
    $manager = User::factory()->create(['roles' => ['GERENCIA'], 'branch_id' => $own->id]);

    $ownReport = FiscalZReport::factory()->create(['branch_id' => $own->id]);
    $otherReport = FiscalZReport::factory()->create(['branch_id' => $other->id]);

    Livewire::actingAs($manager)
        ->test(ListFiscalZReports::class)
        ->assertCanSeeTableRecords([$ownReport])
        ->assertCanNotSeeTableRecords([$otherReport]);
});

test('an X report can be requested from the X reports list', function (): void {
    $printer = FiscalPrinter::factory()->active()->create();

    Livewire::actingAs($this->admin)
        ->test(ListFiscalXReports::class)
        ->callAction('requestXReport', ['fiscal_printer_id' => $printer->id])
        ->assertNotified();

    expect(FiscalDocument::query()
        ->where('fiscal_printer_id', $printer->id)
        ->where('type', FiscalDocumentType::XReport->value)
        ->exists())->toBeTrue();
});
