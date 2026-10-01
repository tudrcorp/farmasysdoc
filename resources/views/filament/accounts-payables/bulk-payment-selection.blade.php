@php
    /** @var \App\Support\Finance\AccountsPayableBulkPaymentPayload $payload */
    $formatUsd = static fn (float $amount): string => number_format($amount, 2, ',', '.').' USD';
    $formatBs = static fn (float $amount): string => 'Bs '.number_format($amount, 2, ',', '.');
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ count($payload->selectedLines) }} factura(s). El total en bolívares es el del listado: factura a la tasa de registro, menos la retención.
        </p>
        <p class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">
            {{ $formatUsd($payload->totalUsd) }} · {{ $formatBs($payload->totalVes) }}
        </p>
    </div>

    <div class="max-h-80 overflow-auto rounded-lg border border-gray-200 dark:border-white/10">
        <table class="min-w-full text-left text-xs">
            <thead class="sticky top-0 bg-gray-50 text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                <tr>
                    <th class="px-2 py-2 font-medium">Proveedor</th>
                    <th class="px-2 py-2 font-medium">Nº factura</th>
                    <th class="px-2 py-2 font-medium">RIF</th>
                    <th class="px-2 py-2 font-medium">Nº orden</th>
                    <th class="px-2 py-2 font-medium">Sucursal</th>
                    <th class="px-2 py-2 font-medium">Emisión</th>
                    <th class="px-2 py-2 font-medium">Vencimiento</th>
                    <th class="px-2 py-2 text-end font-medium">Tasa registro</th>
                    <th class="px-2 py-2 text-end font-medium">Total USD</th>
                    <th class="px-2 py-2 text-end font-medium">Factura Bs</th>
                    <th class="px-2 py-2 text-end font-medium">IVA</th>
                    <th class="px-2 py-2 text-end font-medium">% ret.</th>
                    <th class="px-2 py-2 text-end font-medium">Retenido</th>
                    <th class="px-2 py-2 text-end font-medium">A pagar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($payload->selectedLines as $line)
                    <tr class="text-gray-900 dark:text-gray-100">
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['supplier_name'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['invoice_number'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['rif'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['purchase_number'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['branch_name'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['issued_at_label'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['due_at_label'] }}</td>
                        <td class="px-2 py-1.5 text-end whitespace-nowrap tabular-nums">{{ $line['bcv_rate_label'] }}</td>
                        <td class="px-2 py-1.5 text-end whitespace-nowrap tabular-nums">{{ $line['amount_usd_label'] }}</td>
                        <td class="px-2 py-1.5 text-end whitespace-nowrap tabular-nums">{{ $line['invoice_total_ves_label'] }}</td>
                        <td class="px-2 py-1.5 text-end whitespace-nowrap tabular-nums">{{ $line['tax_caused_label'] }}</td>
                        <td class="px-2 py-1.5 text-end whitespace-nowrap tabular-nums">{{ $line['retention_percent_label'] }}</td>
                        <td class="px-2 py-1.5 text-end whitespace-nowrap tabular-nums">{{ $line['tax_retained_label'] }}</td>
                        <td class="px-2 py-1.5 text-end font-medium whitespace-nowrap tabular-nums">{{ $line['amount_ves_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
