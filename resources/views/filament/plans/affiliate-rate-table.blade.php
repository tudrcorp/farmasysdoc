@php
    /** @var \App\Models\Plan $plan */
    $columns = $plan->usesSubplans() ? $plan->subplans : collect([null]);
@endphp

@if ($plan->affiliateBands->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Sin tramos cargados.</p>
@else
    <div class="overflow-x-auto">
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            {{ $plan->prices_medicines_by_cost_profit
                ? 'Porcentaje de ganancia sobre el costo, según la cantidad de afiliados.'
                : 'USD por afiliado al mes · Contratación por cliente.' }}
        </p>
        <table class="w-full min-w-[28rem] border-separate border-spacing-y-1 text-sm">
            <thead>
                <tr class="border-t-2 border-warning-400">
                    <th class="rounded-s-lg bg-gray-50 px-4 py-3 text-start text-xs font-bold uppercase tracking-wide text-primary-700 dark:bg-white/5 dark:text-primary-400">
                        Afiliados
                    </th>
                    @foreach ($columns as $subplan)
                        <th @class([
                            'bg-gray-50 px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-primary-700 dark:bg-white/5 dark:text-primary-400',
                            'rounded-e-lg' => $loop->last,
                        ])>
                            {{ $plan->prices_medicines_by_cost_profit
                                ? ($subplan?->name ? $subplan->name.' (%)' : 'Porcentaje de ganancia')
                                : ($subplan?->name ?? 'Tarifa') }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($plan->affiliateBands as $band)
                    <tr class="{{ $loop->odd ? 'bg-gray-50/70 dark:bg-white/[0.03]' : '' }}">
                        <td class="rounded-s-lg border-s-4 border-primary-600 px-4 py-3 font-medium text-gray-950 dark:border-primary-400 dark:text-white">
                            {{ $band->label() }}
                        </td>
                        @foreach ($columns as $subplan)
                            @php($price = $band->priceFor($subplan))
                            <td @class([
                                'px-4 py-3 text-center text-base font-bold tabular-nums text-primary-700 dark:text-primary-400',
                                'rounded-e-lg' => $loop->last,
                            ])>
                                {{ $price === null ? '—' : ($plan->prices_medicines_by_cost_profit ? number_format($price, 2, ',', '.').' %' : '$'.number_format($price, 2, ',', '.')) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
