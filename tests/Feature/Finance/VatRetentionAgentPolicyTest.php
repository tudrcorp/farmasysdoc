<?php

use App\Models\FinancialSetting;
use App\Support\Finance\VatRetentionAgentPolicy;

beforeEach(function (): void {
    VatRetentionAgentPolicy::forgetCache();
});

it('retains on every invoice date when no suspension period is configured', function (): void {
    FinancialSetting::current()->update([
        'vat_retention_agent_active' => true,
        'vat_retention_suspended_from' => null,
        'vat_retention_resumed_from' => null,
    ]);

    expect(VatRetentionAgentPolicy::retainsOnInvoiceDate('2026-11-15'))->toBeTrue()
        ->and(VatRetentionAgentPolicy::effectivePercent(75.0, '2026-11-15'))->toBe(75.0);
});

it('stops retaining from the suspension date by invoice date while the agent is off', function (): void {
    FinancialSetting::current()->update([
        'vat_retention_agent_active' => false,
        'vat_retention_suspended_from' => '2026-11-01',
        'vat_retention_resumed_from' => null,
    ]);

    expect(VatRetentionAgentPolicy::retainsOnInvoiceDate('2026-10-31'))->toBeTrue()
        ->and(VatRetentionAgentPolicy::retainsOnInvoiceDate('2026-11-01'))->toBeFalse()
        ->and(VatRetentionAgentPolicy::retainsOnInvoiceDate('2027-02-10'))->toBeFalse()
        ->and(VatRetentionAgentPolicy::effectivePercent(75.0, '2026-11-01'))->toBe(0.0);
});

it('keeps retaining invoices without an invoice date', function (): void {
    FinancialSetting::current()->update([
        'vat_retention_agent_active' => false,
        'vat_retention_suspended_from' => '2026-11-01',
    ]);

    expect(VatRetentionAgentPolicy::retainsOnInvoiceDate(null))->toBeTrue();
});

it('keeps the suspension window when retention is reactivated', function (): void {
    FinancialSetting::current()->update([
        'vat_retention_agent_active' => true,
        'vat_retention_suspended_from' => '2026-11-01',
        'vat_retention_resumed_from' => '2027-03-01',
    ]);

    expect(VatRetentionAgentPolicy::retainsOnInvoiceDate('2026-10-31'))->toBeTrue()
        ->and(VatRetentionAgentPolicy::retainsOnInvoiceDate('2027-02-28'))->toBeFalse()
        ->and(VatRetentionAgentPolicy::retainsOnInvoiceDate('2027-03-01'))->toBeTrue();
});
