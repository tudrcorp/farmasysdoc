@php
    /** @var array<string, mixed> $result */
    $lines = is_array($result['lines'] ?? null) ? $result['lines'] : [];
    $synced = array_values(array_filter($lines, static fn (mixed $line): bool => is_array($line) && ! empty($line['ok'])));
    $failed = array_values(array_filter($lines, static fn (mixed $line): bool => is_array($line) && empty($line['ok'])));
    $formatBs = static fn (float $amount): string => 'Bs '.number_format($amount, 2, ',', '.');
    $previousTotal = round(array_sum(array_map(
        static fn (array $line): float => (float) ($line['previous_balance_ves'] ?? 0),
        $synced,
    )), 2);
    $newTotal = round(array_sum(array_map(
        static fn (array $line): float => (float) ($line['new_balance_ves'] ?? 0),
        $synced,
    )), 2);
@endphp

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Tasa BCV aplicada: <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $result['rate_label'] ?? '—' }} Bs/USD</span>.
        Solo cambió el total a pagar. Factura, IVA, retención y tasa de registro quedan igual.
    </p>

    @if ($synced !== [])
        <div class="max-h-80 overflow-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="min-w-full text-left text-xs">
                <thead class="sticky top-0 bg-gray-50 text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-2 py-2 font-medium">Proveedor</th>
                        <th class="px-2 py-2 font-medium">Nº factura</th>
                        <th class="px-2 py-2 text-end font-medium">Saldo anterior</th>
                        <th class="px-2 py-2 text-end font-medium">USD</th>
                        <th class="px-2 py-2 text-end font-medium">Saldo nuevo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($synced as $line)
                        <tr class="text-gray-900 dark:text-gray-100">
                            <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['supplier'] }}</td>
                            <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['invoice'] }}</td>
                            <td class="px-2 py-1.5 text-end whitespace-nowrap tabular-nums">{{ $formatBs((float) $line['previous_balance_ves']) }}</td>
                            <td class="px-2 py-1.5 text-end whitespace-nowrap tabular-nums">{{ number_format((float) $line['payable_usd'], 2, ',', '.') }}</td>
                            <td class="px-2 py-1.5 text-end font-semibold whitespace-nowrap tabular-nums text-green-700 dark:text-green-400">{{ $formatBs((float) $line['new_balance_ves']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-gray-200 bg-gray-50 font-semibold dark:border-white/10 dark:bg-gray-900">
                    <tr>
                        <td class="px-2 py-2" colspan="2">Total</td>
                        <td class="px-2 py-2 text-end whitespace-nowrap tabular-nums">{{ $formatBs($previousTotal) }}</td>
                        <td class="px-2 py-2"></td>
                        <td class="px-2 py-2 text-end whitespace-nowrap tabular-nums">{{ $formatBs($newTotal) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    @if ($failed !== [])
        <div class="space-y-2">
            <p class="text-sm font-medium text-red-700 dark:text-red-400">No se modificaron {{ count($failed) }} cuenta(s).</p>
            <div class="max-h-48 overflow-auto rounded-lg border border-red-200 dark:border-red-500/30">
                <table class="min-w-full text-left text-xs">
                    <thead class="sticky top-0 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300">
                        <tr>
                            <th class="px-2 py-2 font-medium">Proveedor</th>
                            <th class="px-2 py-2 font-medium">Nº factura</th>
                            <th class="px-2 py-2 font-medium">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100 dark:divide-red-500/20">
                        @foreach ($failed as $line)
                            <tr class="text-gray-900 dark:text-gray-100">
                                <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['supplier'] }}</td>
                                <td class="px-2 py-1.5 whitespace-nowrap">{{ $line['invoice'] }}</td>
                                <td class="px-2 py-1.5">{{ $line['error'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
