@php
    /** @var callable $get */
    $err = $get('_bulk_error');
    $lines = json_decode((string) ($get('_lines_payload_json') ?? '[]'), true);
    if (! is_array($lines)) {
        $lines = [];
    }
    $rate = (float) ($get('_bcv_rate') ?? 0);
    $totalUsd = (float) ($get('_total_usd') ?? 0);
    $totalVes = (float) ($get('_total_ves') ?? 0);
@endphp

<div class="space-y-4 text-sm">
    @if (filled($err))
        <div class="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-red-950 dark:border-red-600/50 dark:bg-red-950/40 dark:text-red-50">
            {{ $err }}
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-gray-800 dark:border-white/10 dark:bg-white/5 dark:text-gray-100">
            <p class="font-semibold">Tasa BCV del día actual (promedio oficial)</p>
            <p class="mt-1 font-mono tabular-nums">{{ $rate > 0 ? number_format($rate, 2, ',', '.').' Bs/USD' : '—' }}</p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[640px] text-left text-xs">
                <thead class="bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                    <tr>
                        <th class="px-2 py-2 font-semibold">CxP</th>
                        <th class="px-2 py-2 font-semibold">OC</th>
                        <th class="px-2 py-2 font-semibold">Proveedor</th>
                        <th class="px-2 py-2 font-semibold">Factura</th>
                        <th class="px-2 py-2 text-end font-semibold">USD</th>
                        <th class="px-2 py-2 text-end font-semibold">Bs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($lines as $row)
                        <tr class="bg-white dark:bg-transparent">
                            <td class="px-2 py-1.5 font-mono">#{{ (int) ($row['id'] ?? 0) }}</td>
                            <td class="px-2 py-1.5">{{ e($row['purchase_number'] ?? '—') }}</td>
                            <td class="px-2 py-1.5">{{ e($row['supplier_name'] ?? '—') }}</td>
                            <td class="px-2 py-1.5">{{ e($row['supplier_invoice_number'] ?? '—') }}</td>
                            <td class="px-2 py-1.5 text-end font-mono tabular-nums">{{ number_format((float) ($row['usd'] ?? 0), 2, ',', '.') }}</td>
                            <td class="px-2 py-1.5 text-end font-mono tabular-nums">{{ number_format((float) ($row['ves'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-gray-900 dark:bg-white/5 dark:text-white">
                    <tr>
                        <td class="px-2 py-2" colspan="4">Totales seleccionados</td>
                        <td class="px-2 py-2 text-end font-mono tabular-nums">{{ number_format($totalUsd, 2, ',', '.') }} USD</td>
                        <td class="px-2 py-2 text-end font-mono tabular-nums">Bs {{ number_format($totalVes, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
