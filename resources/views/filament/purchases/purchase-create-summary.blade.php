@php
    /** @var array<string, mixed> $summary */
    $pfx = $summary['currency_prefix'] ?? '$';
    $money = static function (float $v) use ($pfx): string {
        return $pfx . number_format($v, 2, ',', '.');
    };
@endphp

<div class="space-y-4 text-sm text-gray-950 dark:text-white">
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Proveedor</span>
            <p class="mt-0.5 font-semibold">{{ e($summary['supplier_label']) }}</p>
        </div>
        <div>
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Pago al proveedor</span>
            <p class="mt-0.5 font-semibold">{{ e($summary['payment_status_label']) }}</p>
        </div>
        <div>
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Moneda de carga</span>
            <p class="mt-0.5 font-semibold">{{ e($summary['entry_currency'] ?? 'USD') }}</p>
        </div>
        <div>
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">N° factura / control</span>
            <p class="mt-0.5 font-semibold">{{ e($summary['supplier_invoice_number']) }} · {{ e($summary['supplier_control_number']) }}</p>
        </div>
        <div>
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Fechas</span>
            <p class="mt-0.5 font-semibold">Factura: {{ e($summary['supplier_invoice_date']) }} — Carga: {{ e($summary['registered_in_system_date']) }}</p>
        </div>
    </div>

    <div>
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Productos cargados</h3>
        <div class="max-h-[min(50vh,22rem)] overflow-x-auto overflow-y-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[720px] divide-y divide-gray-200 text-xs dark:divide-white/10">
                <thead class="sticky top-0 bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-2 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">#</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">Producto</th>
                        <th class="px-2 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Costo</th>
                        <th class="px-2 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Cant.</th>
                        <th class="px-2 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Desc. %</th>
                        <th class="px-2 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">IVA %</th>
                        <th class="px-2 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">IVA $</th>
                        <th class="px-2 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Subtotal</th>
                        <th class="px-2 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Total</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">Venc.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($summary['rows'] as $row)
                        <tr>
                            <td class="whitespace-nowrap px-2 py-1.5 tabular-nums text-gray-600 dark:text-gray-400">{{ $row['index'] }}</td>
                            <td class="px-2 py-1.5">{{ e($row['product_label']) }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">{{ $money($row['unit_cost']) }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">{{ number_format($row['quantity'], 3, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">{{ number_format($row['line_discount_percent'], 2, ',', '.') }}%</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">{{ number_format($row['line_vat_percent'], 2, ',', '.') }}%</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">{{ $money($row['tax_amount']) }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">{{ $money($row['line_subtotal']) }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-right font-medium tabular-nums">{{ $money($row['line_total']) }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-center text-gray-600 dark:text-gray-400">{{ e($row['lot_expiration']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-2 py-4 text-center text-gray-500 dark:text-gray-400">No hay líneas de producto en esta compra.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-3 dark:border-white/10 dark:bg-white/5">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Totales del documento</h3>
        <dl class="grid gap-2 sm:grid-cols-2">
            <div class="flex justify-between gap-2 sm:col-span-2">
                <dt class="text-gray-600 dark:text-gray-400">Total declarado (proveedor)</dt>
                <dd class="font-medium tabular-nums">{{ $money((float) ($summary['declared_invoice_total'] ?? 0)) }}</dd>
            </div>
            <div class="flex justify-between gap-2 sm:col-span-2">
                <dt class="text-gray-600 dark:text-gray-400">Subtotal líneas</dt>
                <dd class="font-medium tabular-nums">{{ $money($summary['subtotal']) }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-gray-600 dark:text-gray-400">Descuento en líneas</dt>
                <dd class="tabular-nums">{{ $money($summary['discount_total_lines']) }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-gray-600 dark:text-gray-400">Desc. subtotal %</dt>
                <dd class="tabular-nums">{{ number_format($summary['document_discount_percent'], 2, ',', '.') }}%</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-gray-600 dark:text-gray-400">Monto desc. documento</dt>
                <dd class="tabular-nums">{{ $money($summary['document_discount_amount']) }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-gray-600 dark:text-gray-400">Base exenta (tras desc.)</dt>
                <dd class="tabular-nums">{{ $money($summary['net_exempt_after_document_discount']) }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-gray-600 dark:text-gray-400">Base imponible (tras desc.)</dt>
                <dd class="tabular-nums">{{ $money($summary['net_taxable_after_document_discount']) }}</dd>
            </div>
            <div class="flex justify-between gap-2 sm:col-span-2">
                <dt class="text-gray-600 dark:text-gray-400">IVA (tasa global {{ number_format($summary['global_vat_percent'], 2, ',', '.') }}%)</dt>
                <dd class="tabular-nums">{{ $money($summary['tax_total']) }}</dd>
            </div>
            <div class="flex justify-between gap-2 border-t border-gray-200 pt-2 dark:border-white/10 sm:col-span-2">
                <dt class="font-semibold">Total compra</dt>
                <dd class="text-base font-bold tabular-nums text-primary-600 dark:text-primary-400">{{ $money($summary['total']) }}</dd>
            </div>
        </dl>
    </div>
</div>
