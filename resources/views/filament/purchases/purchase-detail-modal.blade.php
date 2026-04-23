@php
    /** @var \App\Models\Purchase $purchase */
    $moneyPfx = $purchase->documentMoneyPrefix();
    $supplierName = $purchase->supplier
        ? ($purchase->supplier->trade_name ?: $purchase->supplier->legal_name)
        : '—';
    $pdfUrl = route('purchases.document-pdf', $purchase);
    $defaultVatRatePercent = \App\Support\Finance\DefaultVatRate::percent();
    /** Totales coherentes con las líneas cargadas (el encabezado en BD puede estar en 0 en compras antiguas). */
    $documentTotals = $purchase->expectedHeaderTotalsFromItems();
@endphp

<div class="space-y-6">
    <div class="rounded-xl border-2 border-primary-600/30 bg-primary-50/80 p-4 dark:border-primary-500/40 dark:bg-primary-950/40">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
            Datos de la factura del proveedor
        </p>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Nº de factura</dt>
                <dd class="mt-1 text-lg font-bold text-gray-950 dark:text-white">
                    {{ filled($purchase->supplier_invoice_number) ? $purchase->supplier_invoice_number : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Nº de control</dt>
                <dd class="mt-1 text-lg font-bold text-gray-950 dark:text-white">
                    {{ filled($purchase->supplier_control_number) ? $purchase->supplier_control_number : '—' }}
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Proveedor</dt>
                <dd class="mt-1 text-lg font-bold text-gray-950 dark:text-white">
                    {{ $supplierName }}
                </dd>
                @if ($purchase->supplier && filled($purchase->supplier->trade_name) && filled($purchase->supplier->legal_name) && $purchase->supplier->trade_name !== $purchase->supplier->legal_name)
                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ $purchase->supplier->legal_name }}</p>
                @endif
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Fecha de la factura</dt>
                <dd class="mt-1 text-lg font-bold text-gray-950 dark:text-white">
                    {{ $purchase->supplier_invoice_date?->format('d/m/Y') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Fecha de carga en el sistema</dt>
                <dd class="mt-1 text-lg font-bold text-gray-950 dark:text-white">
                    {{ $purchase->registered_in_system_date?->format('d/m/Y') ?? '—' }}
                </dd>
            </div>
        </dl>
    </div>

    <div class="grid gap-4 text-sm sm:grid-cols-2">
        <div>
            <span class="text-gray-500 dark:text-gray-400">Nº orden interna</span>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $purchase->purchase_number }}</p>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">Sucursal de recepción</span>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $purchase->branch?->name ?? '—' }}</p>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">Estado</span>
            <p class="font-semibold text-gray-900 dark:text-white">
                {{ $purchase->status instanceof \App\Enums\PurchaseStatus ? $purchase->status->label() : (string) $purchase->status }}
            </p>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">Pago al proveedor</span>
            <p class="font-semibold text-gray-900 dark:text-white">{{ \App\Support\Purchases\PurchasePaymentStatus::label($purchase->payment_status) }}</p>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">Moneda registrada</span>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $purchase->entryCurrency()->value }}</p>
        </div>
        @if ($purchase->entryCurrency() === \App\Enums\PurchaseEntryCurrency::VES && filled($purchase->official_usd_ves_rate))
            <div class="sm:col-span-2">
                <span class="text-gray-500 dark:text-gray-400">Tasa oficial usada (Bs por USD)</span>
                <p class="font-mono font-semibold text-gray-900 dark:text-white">{{ number_format((float) $purchase->official_usd_ves_rate, 8, ',', '.') }}</p>
            </div>
        @endif
    </div>

    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Productos</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[880px] divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">#</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Producto</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Costo unit.</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Cant.</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Desc. %</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Tasa IVA %</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">IVA (monto)</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Subtotal línea</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Total línea</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($purchase->items as $line)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-300">{{ $line->line_number ?? $loop->iteration }}</td>
                            <td class="px-3 py-2 text-gray-900 dark:text-white">
                                <div class="font-medium">{{ $line->product_name_snapshot ?? '—' }}</div>
                                @if (filled($line->sku_snapshot))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $line->sku_snapshot }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $moneyPfx }}{{ number_format((float) $line->unit_cost, 2, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ number_format((float) $line->quantity_ordered, 3, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ number_format((float) $line->line_discount_percent, 2, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ number_format((float) $line->line_vat_percent, 2, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $moneyPfx }}{{ number_format((float) $line->tax_amount, 2, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $moneyPfx }}{{ number_format((float) $line->line_subtotal, 2, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-medium tabular-nums text-gray-950 dark:text-white">{{ $moneyPfx }}{{ number_format((float) $line->line_total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-6 text-center text-gray-500">Sin productos en esta compra.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <p class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">
            Totales del documento
        </p>
        <dl class="grid gap-2 text-sm sm:max-w-xl sm:grid-cols-[1fr_auto] sm:gap-x-8 sm:gap-y-2">
            @if (filled($purchase->declared_invoice_total))
                <dt class="text-gray-600 dark:text-gray-400">Total declarado (proveedor)</dt>
                <dd class="text-end font-semibold tabular-nums text-gray-900 dark:text-white">{{ $moneyPfx }}{{ number_format((float) $purchase->declared_invoice_total, 2, ',', '.') }}</dd>
            @endif
            <dt class="text-gray-600 dark:text-gray-400">Subtotal</dt>
            <dd class="text-end font-semibold tabular-nums text-gray-900 dark:text-white">{{ $moneyPfx }}{{ number_format((float) ($documentTotals['subtotal'] ?? 0), 2, ',', '.') }}</dd>
            <dt class="text-gray-600 dark:text-gray-400">Descuento de subtotal</dt>
            <dd class="text-end font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format((float) ($purchase->document_discount_percent ?? 0), 2, ',', '.') }}%</dd>
            <dt class="text-gray-600 dark:text-gray-400">Base (productos sin IVA)</dt>
            <dd class="text-end font-semibold tabular-nums text-gray-900 dark:text-white">{{ $moneyPfx }}{{ number_format((float) ($documentTotals['net_exempt_after_document_discount'] ?? 0), 2, ',', '.') }}</dd>
            <dt class="text-gray-600 dark:text-gray-400">Base imponible</dt>
            <dd class="text-end font-semibold tabular-nums text-gray-900 dark:text-white">{{ $moneyPfx }}{{ number_format((float) ($documentTotals['net_taxable_after_document_discount'] ?? 0), 2, ',', '.') }}</dd>
            <dt class="text-gray-600 dark:text-gray-400">Cálculo del IVA</dt>
            <dd class="text-end font-semibold tabular-nums text-gray-900 dark:text-white">{{ $moneyPfx }}{{ number_format((float) ($documentTotals['tax_total'] ?? 0), 2, ',', '.') }}</dd>
            <dt class="border-t border-gray-200 pt-2 font-semibold text-gray-900 dark:border-white/10 dark:text-white sm:col-span-1">Total</dt>
            <dd class="border-t border-gray-200 pt-2 text-end text-lg font-bold tabular-nums text-amber-600 dark:text-amber-400 sm:col-span-1">{{ $moneyPfx }}{{ number_format((float) ($documentTotals['total'] ?? 0), 2, ',', '.') }}</dd>
        </dl>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            IVA al {{ number_format((float) $defaultVatRatePercent, 2, ',', '.') }}% sobre la base imponible. Los importes de IVA por línea son solo referencia por ítem.
        </p>
    </div>

    @if (filled($purchase->notes))
        <div class="text-sm">
            <span class="font-semibold text-gray-700 dark:text-gray-200">Notas</span>
            <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $purchase->notes }}</p>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
        <a
            href="{{ $pdfUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-btn-size-md fi-btn-color-primary fi-color-custom gap-1.5 px-3 py-2 text-sm inline-flex bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50 fi-ac-btn-action"
            style="--c-400:var(--primary-400);--c-500:var(--primary-500);--c-600:var(--primary-600);"
        >
            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096M18.66 18c.24-.229.48-.468.72-.721M15.66 12H8.34m7.32 0c.24-.03.48-.062.72-.096M15.66 12c.24-.229.48-.468.72-.721m0 0 1.02-2.32a1.125 1.125 0 0 0-1.02-1.59H8.34m0 0L7.32 9.279m0 0a48.11 48.11 0 0 1 3.478-.397m4.932 3.224h-.003M15.66 15H8.34m7.32 0 1.026 2.32a1.125 1.125 0 0 1-1.02 1.59H8.34a1.125 1.125 0 0 1-1.02-1.59l1.026-2.32" />
            </svg>
            Imprimir PDF
        </a>
    </div>
</div>
