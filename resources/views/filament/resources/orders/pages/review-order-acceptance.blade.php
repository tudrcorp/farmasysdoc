@php
    $acceptance = $this->acceptance();
    $preview = $this->preview();
    $rows = $preview['rows'];
    $rateLabel = $acceptance !== null
        ? number_format((float) $acceptance->bcv_ves_per_usd, 2, ',', '.')
        : ($this->rate !== null ? number_format($this->rate, 2, ',', '.') : null);
@endphp

<x-filament-panels::page>
    @if ($acceptance)
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total aceptado USD</p>
                <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $this->money((float) $acceptance->accepted_total_usd) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total aceptado VES</p>
                <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Bs. {{ number_format((float) $acceptance->accepted_total_ves, 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tasa BCV</p>
                <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $rateLabel }} Bs. por USD</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nota {{ $acceptance->delivery_note_number }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Medicamento</th>
                        <th class="px-4 py-3">Pedido</th>
                        <th class="px-4 py-3">Existencia</th>
                        <th class="px-4 py-3 text-right">Precio</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Decisión</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($acceptance->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-950 dark:text-white">{{ $item->product_name }}</div>
                                <div class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item->barcode ?: 'Sin código de barra' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $this->quantity((float) $item->quantity_ordered) }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $this->quantity((float) $item->quantity_on_hand) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-200">{{ $this->money((float) $item->unit_price_usd) }}</td>
                            <td class="px-4 py-3 text-right text-gray-950 dark:text-white">
                                @if ($item->accepted)
                                    {{ $this->money((float) $item->line_total_usd) }}
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Bs. {{ number_format((float) $item->line_total_ves, 2, ',', '.') }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($item->accepted)
                                    <span class="font-medium text-emerald-600 dark:text-emerald-400">Se envía</span>
                                @else
                                    <span class="font-medium text-red-600 dark:text-red-400">No se envía</span>
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $item->rejection_note }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        @if ($message = $this->blockedMessage())
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                {{ $message }}
            </div>
        @endif

        @if ($this->canDecide())
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total del pedido</p>
                    <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $this->money($this->orderTotalUsd()) }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->bolivars($this->orderTotalUsd()) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total a enviar</p>
                    <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $this->money($this->selectedTotalUsd()) }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->bolivars($this->selectedTotalUsd()) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tasa BCV</p>
                    <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $rateLabel }} Bs. por USD</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Puede marcar Enviar aunque la existencia no alcance. La nota va en el botón Nota de la fila, o en Agregar nota si selecciona varios productos.</p>
                </div>
            </div>

            {{ $this->table }}
        @elseif ($rows !== [])
            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Medicamento</th>
                            <th class="px-4 py-3">Pedido</th>
                            <th class="px-4 py-3">En Las Delicias</th>
                            <th class="px-4 py-3 text-right">Precio venta</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</div>
                                    <div class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $row['barcode'] !== '' ? $row['barcode'] : 'Sin código de barra' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $this->quantity($row['quantity']) }}</td>
                                <td class="px-4 py-3">{{ $this->quantity($row['on_hand']) }}</td>
                                <td class="px-4 py-3 text-right">{{ $this->money($row['unit_price_usd']) }}</td>
                                <td class="px-4 py-3 text-right">{{ $this->money($row['line_total_usd']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
