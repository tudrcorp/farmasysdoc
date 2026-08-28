<?php

use App\Models\Branch;
use App\Models\FiscalCompanySetting;
use App\Models\Sale;
use App\Services\Fiscal\ThermalFiscalReceiptFormatter;
use App\Support\Fiscal\CompanyFiscalAddress;

beforeEach(function (): void {
    CompanyFiscalAddress::forgetCache();
});

test('fiscal receipt includes company and branch addresses', function (): void {
    $companyAddress = 'AV EMPRESA PRINCIPAL CASA 1 BARINAS';
    $branchAddress = 'CALLE SUCURSAL LOCAL 12';

    $setting = FiscalCompanySetting::current();
    $setting->address = $companyAddress;
    $setting->save();

    $branch = Branch::factory()->create([
        'legal_name' => 'FARMACIA PRUEBA C.A.',
        'tax_id' => 'J410867655',
        'address' => $branchAddress,
        'city' => 'Barinas',
        'state' => 'Barinas',
    ]);

    $sale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'bcv_ves_per_usd' => 36.5,
    ]);
    $sale->setRelation('branch', $branch);
    $sale->setRelation('client', null);
    $sale->setRelation('items', collect());

    $plain = app(ThermalFiscalReceiptFormatter::class)->format($sale);

    expect($plain)->toContain(mb_strtoupper($companyAddress))
        ->and($plain)->toContain(mb_strtoupper($branchAddress));
});
