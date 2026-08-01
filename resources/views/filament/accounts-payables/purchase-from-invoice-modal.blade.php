@php
    /** @var \App\Models\Purchase|null $purchase */
    /** @var \App\Models\AccountsPayable $accountsPayable */
    /** @var \App\Support\Finance\AccountsPayableInvoiceTaxSnapshot $taxSnapshot */
    $formatBs = static fn (float $amount): string => 'Bs '.number_format($amount, 2, ',', '.');
    $supplierName = $accountsPayable->supplier_name
        ?: ($purchase?->supplier?->displayName() ?? '—');
@endphp

<div class="space-y-4">
    <div class="text-sm text-gray-600 dark:text-gray-300">
        <p>
            <span class="font-medium text-gray-900 dark:text-white">{{ $supplierName }}</span>
            @if (filled($accountsPayable->supplier_tax_id))
                <span class="text-gray-500 dark:text-gray-400">· {{ $accountsPayable->supplier_tax_id }}</span>
            @endif
        </p>
        @if (filled($taxSnapshot->purchaseNumber))
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Compra {{ $taxSnapshot->purchaseNumber }}
            </p>
        @endif
    </div>

    <dl class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Total factura (USD)</dt>
            <dd class="mt-1 text-base font-bold tabular-nums text-gray-950 dark:text-white">
                {{ number_format((float) $accountsPayable->purchase_total_usd, 2, ',', '.') }} USD
            </dd>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Total factura (Bs)</dt>
            <dd class="mt-1 text-base font-bold tabular-nums text-gray-950 dark:text-white">
                {{ $formatBs((float) $accountsPayable->purchase_total_ves_at_issue) }}
            </dd>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">IVA factura</dt>
            <dd class="mt-1 text-base font-bold tabular-nums text-gray-950 dark:text-white">
                {{ $taxSnapshot->taxCausedVes !== null ? $formatBs((float) $taxSnapshot->taxCausedVes) : '—' }}
            </dd>
            @if ($taxSnapshot->vatRatePercent !== null)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Alícuota {{ number_format((float) $taxSnapshot->vatRatePercent, 0, ',', '.') }}%
                </p>
            @endif
        </div>
        <div class="rounded-lg border border-warning-600/30 bg-warning-50/80 p-3 dark:border-warning-500/40 dark:bg-warning-950/30">
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Total retenido</dt>
            <dd class="mt-1 text-base font-bold tabular-nums text-warning-600 dark:text-warning-400">
                {{ $taxSnapshot->taxRetainedVes !== null ? $formatBs((float) $taxSnapshot->taxRetainedVes) : '—' }}
            </dd>
            @if ($taxSnapshot->retentionPercent !== null)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format((float) $taxSnapshot->retentionPercent, 0, ',', '.') }}% SENIAT
                </p>
            @endif
        </div>
        <div class="rounded-lg border border-success-600/30 bg-success-50/80 p-4 sm:col-span-2 dark:border-success-500/40 dark:bg-success-950/30">
            <dt class="text-xs font-medium text-success-700 dark:text-success-300">Total a pagar</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-success-700 dark:text-success-300">
                {{ $formatBs(\App\Support\Finance\AccountsPayableInvoiceTaxSnapshot::amountPayableVes($accountsPayable)) }}
            </dd>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total factura (Bs) − retención</p>
        </div>
    </dl>
</div>
