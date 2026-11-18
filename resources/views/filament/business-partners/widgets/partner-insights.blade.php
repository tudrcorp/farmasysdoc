@php
    $money = fn (mixed $value): string => '$'.number_format((float) $value, 2, ',', '.');
    $axisMoney = function (float $value): string {
        return match (true) {
            $value >= 1_000_000 => '$'.number_format($value / 1_000_000, 1, ',', '.').'M',
            $value >= 1_000 => '$'.number_format($value / 1_000, $value >= 10_000 ? 0 : 1, ',', '.').'k',
            $value > 0 && $value < 10 => '$'.number_format($value, 2, ',', '.'),
            default => '$'.number_format($value, 0, ',', '.'),
        };
    };

    $orderAmounts = array_map('floatval', $series['amounts']);
    $consumptionAmounts = array_map('floatval', $series['consumption_amounts'] ?? array_fill(0, count($orderAmounts), 0));
    $orderCounts = array_map('intval', $series['orders']);
    $consumptionCounts = array_map('intval', $series['consumptions'] ?? array_fill(0, count($orderCounts), 0));
    $amounts = array_map(fn ($a, $b) => round($a + $b, 2), $orderAmounts, $consumptionAmounts);
    $orders = array_map(fn ($a, $b) => $a + $b, $orderCounts, $consumptionCounts);
    $hasConsumptions = array_sum($consumptionCounts) > 0;
    $labels = $series['labels'];
    $months = count($labels);
    $maxAmount = max($amounts ?: [0]);
    $maxOrders = max($orders ?: [0]);
    $hasTrend = $maxAmount > 0 || $maxOrders > 0;

    /** Techo «redondo» del eje para que las líneas guía caigan en cifras limpias. */
    $niceCeil = function (float $value): float {
        if ($value <= 0) {
            return 1;
        }
        $magnitude = 10 ** floor(log10($value));
        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($value <= $step * $magnitude) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    };
    $ceilAmount = $niceCeil($maxAmount);
    $ceilOrders = max(1, $niceCeil((float) $maxOrders));
    $linePoints = [];
    foreach ($orders as $i => $value) {
        $x = $months > 0 ? round(($i + 0.5) / $months * 100, 3) : 0;
        $y = round(100 - ($value / $ceilOrders) * 100, 3);
        $linePoints[] = ['x' => $x, 'y' => $y];
    }
    $totalAmount = array_sum($amounts);
    $totalOrders = array_sum($orders);

    $statusColors = ['warning' => '#F59E0B', 'info' => '#0EA5E9', 'success' => '#10B981'];
    $statusTotal = array_sum(array_column($statuses, 'count'));
    $circ = 2 * M_PI * 42;
    $offset = 0;
    $segments = [];
    foreach ($statuses as $status) {
        $length = $statusTotal > 0 ? $circ * $status['count'] / $statusTotal : 0;
        $segments[] = [...$status, 'length' => $length, 'offset' => $offset, 'hex' => $statusColors[$status['color']] ?? '#94A3B8', 'percent' => $statusTotal > 0 ? (int) round($status['count'] / $statusTotal * 100) : 0];
        $offset += $length;
    }

    $quotas = array_values(array_filter([
        $credit ? ['title' => 'Línea de crédito', 'icon' => 'heroicon-o-credit-card', 'used' => $credit['consumed'], 'total' => $credit['ceiling'], 'left' => $credit['available'], 'percent' => (float) ($credit['used_percent'] ?? 0), 'hint' => 'Pedidos a crédito finalizados'] : null,
        $fund ? ['title' => 'Fondo de consumo', 'icon' => 'heroicon-o-wallet', 'used' => $fund['consumed'], 'total' => $fund['authorized'], 'left' => $fund['remaining'], 'percent' => (float) $fund['used_percent'], 'hint' => 'Notas de entrega por convenio'] : null,
    ]));
@endphp

<x-filament-widgets::widget>
    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Tendencia 12 meses --}}
        <section class="dale-card p-5 sm:p-6 lg:col-span-2" aria-labelledby="dale-trend">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="dale-trend" class="text-base font-semibold text-gray-950 dark:text-white">Actividad de los últimos 12 meses</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $hasConsumptions ? 'Monto de pedidos y consumos por convenio (barras) y cantidad de movimientos (línea).' : 'Monto de pedidos (barras) y cantidad de pedidos (línea).' }}</p>
                </div>
                <dl class="flex gap-6 text-right">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Monto total</dt>
                        <dd class="dale-figures text-lg font-semibold text-gray-950 dark:text-white">{{ $money($totalAmount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $hasConsumptions ? 'Movimientos' : 'Pedidos' }}</dt>
                        <dd class="dale-figures text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($totalOrders, 0, ',', '.') }}</dd>
                    </div>
                </dl>
            </header>

            <div class="relative mt-6">
                <div class="flex">
                    {{-- Eje de montos --}}
                    <div class="dale-figures flex h-[17rem] w-12 shrink-0 flex-col justify-between pe-2 text-right text-[11px] text-gray-400" aria-hidden="true">
                        @foreach ([1, 0.75, 0.5, 0.25, 0] as $fraction)
                            <span @class(['-translate-y-1/2 leading-none first:translate-y-0 last:-translate-y-full', 'invisible' => ! $hasTrend])>{{ $axisMoney($ceilAmount * $fraction) }}</span>
                        @endforeach
                    </div>

                    <div class="relative h-[17rem] flex-1">
                        {{-- Líneas guía --}}
                        <div class="pointer-events-none absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                            @foreach (range(1, 5) as $line)
                                <div class="border-t border-dashed border-gray-200 dark:border-white/10 {{ $loop->last ? 'border-solid' : '' }}"></div>
                            @endforeach
                        </div>

                        {{-- Barras --}}
                        <div class="absolute inset-0 flex items-end gap-1.5 sm:gap-2.5">
                            @foreach ($amounts as $i => $amount)
                                @php $height = $ceilAmount > 0 ? max($amount > 0 ? 2 : 0, $amount / $ceilAmount * 100) : 0; @endphp
                                <div class="group relative flex h-full flex-1 items-end justify-center">
                                    @php
                                        $consumptionShare = $amount > 0 ? $consumptionAmounts[$i] / $amount * 100 : 0;
                                    @endphp
                                    <div class="flex w-full max-w-9 flex-col overflow-hidden rounded-t-lg opacity-90 transition-all duration-300 group-hover:opacity-100" style="height: {{ round($height, 2) }}%">
                                        @if ($consumptionAmounts[$i] > 0)
                                            <div class="w-full bg-gradient-to-t from-violet-600 to-violet-400" style="height: {{ round($consumptionShare, 2) }}%"></div>
                                        @endif
                                        <div class="w-full flex-1 bg-gradient-to-t from-primary-600 to-primary-400 dark:from-primary-700 dark:to-primary-400"></div>
                                    </div>
                                    <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-950 px-2.5 py-1.5 text-xs text-white shadow-lg group-hover:block dark:bg-white dark:text-gray-950">
                                        <p class="font-semibold">{{ $labels[$i] }} · {{ $money($amount) }}</p>
                                        <p class="dale-figures">Pedidos: {{ $orderCounts[$i] }} · {{ $money($orderAmounts[$i]) }}</p>
                                        @if ($hasConsumptions)
                                            <p class="dale-figures">Consumos: {{ $consumptionCounts[$i] }} · {{ $money($consumptionAmounts[$i]) }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Línea de pedidos --}}
                        @if ($maxOrders > 0)
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="pointer-events-none absolute inset-0 h-full w-full overflow-visible" aria-hidden="true">
                                <polyline points="{{ collect($linePoints)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}" fill="none" stroke="#F59E0B" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
                            </svg>
                            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                                @foreach ($linePoints as $point)
                                    <span class="absolute h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-amber-500 dark:border-gray-900" style="left: {{ $point['x'] }}%; top: {{ $point['y'] }}%"></span>
                                @endforeach
                            </div>
                        @endif

                        @unless ($hasTrend)
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                    <x-filament::icon icon="heroicon-o-chart-bar" class="h-6 w-6" />
                                </span>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Aún no hay movimientos en este período</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Cuando haya pedidos o consumos por convenio, aquí verá su evolución mes a mes.</p>
                                @if ($createOrderUrl)
                                    <a href="{{ $createOrderUrl }}" wire:navigate class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700">
                                        <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" /> Crear pedido
                                    </a>
                                @endif
                            </div>
                        @endunless
                    </div>
                </div>

                {{-- Meses --}}
                <div class="ms-12 mt-2 flex gap-1.5 sm:gap-2.5" aria-hidden="true">
                    @foreach ($labels as $i => $label)
                        <span @class(['flex-1 truncate text-center text-[11px] text-gray-500 dark:text-gray-400', 'hidden sm:block' => $i % 2 === 1])>{{ $label }}</span>
                    @endforeach
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-600 dark:text-gray-300">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary-500"></span> Pedidos (USD)</span>
                    @if ($hasConsumptions)
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-violet-500"></span> Consumos por convenio (USD)</span>
                    @endif
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> {{ $hasConsumptions ? 'Movimientos' : 'Pedidos' }}</span>
                </div>
            </div>
        </section>

        <div class="grid content-start gap-5">
            {{-- Pedidos por estado --}}
            <section class="dale-card p-5 sm:p-6" aria-labelledby="dale-status">
                <h2 id="dale-status" class="text-base font-semibold text-gray-950 dark:text-white">Pedidos por estado</h2>
                <div class="mt-4 flex items-center gap-5">
                    <div class="relative h-32 w-32 shrink-0" role="img" aria-label="{{ $statusTotal }} pedidos en total">
                        <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                            <circle cx="50" cy="50" r="42" fill="none" stroke-width="12" class="stroke-gray-100 dark:stroke-white/10" />
                            @foreach ($segments as $segment)
                                @if ($segment['length'] > 0)
                                    <circle cx="50" cy="50" r="42" fill="none" stroke-width="12" stroke="{{ $segment['hex'] }}"
                                        stroke-dasharray="{{ round(max(0, $segment['length'] - 1.5), 2) }} {{ round($circ, 2) }}"
                                        stroke-dashoffset="{{ round(-$segment['offset'], 2) }}" />
                                @endif
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="dale-figures text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($statusTotal, 0, ',', '.') }}</span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">pedidos</span>
                        </div>
                    </div>
                    <ul class="min-w-0 flex-1 space-y-2.5">
                        @foreach ($segments as $segment)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="inline-flex min-w-0 items-center gap-2 text-gray-700 dark:text-gray-200">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $segment['hex'] }}"></span>
                                    <span class="truncate">{{ $segment['label'] }}</span>
                                </span>
                                <span class="dale-figures shrink-0 text-gray-500 dark:text-gray-400"><span class="font-semibold text-gray-950 dark:text-white">{{ $segment['count'] }}</span> · {{ $segment['percent'] }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Cupos --}}
            @foreach ($quotas as $quota)
                @php
                    $percent = max(0, min(100, (float) $quota['percent']));
                    $barWidth = $percent > 0 ? max(1.5, $percent) : 0;
                    $barTone = $percent >= 90 ? 'from-danger-500 to-danger-400' : ($percent >= 75 ? 'from-warning-500 to-warning-400' : 'from-primary-600 to-primary-400');
                @endphp
                <section class="dale-card p-5" aria-label="{{ $quota['title'] }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                <x-filament::icon :icon="$quota['icon']" class="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $quota['title'] }}</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $quota['hint'] }}</p>
                            </div>
                        </div>
                        <span class="dale-figures text-sm font-semibold text-gray-950 dark:text-white">{{ \App\Support\Partners\PartnerPanel::percent($percent) }}</span>
                    </div>
                    <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ round($percent) }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $quota['title'] }} usado">
                        <div class="dale-progress-bar h-full rounded-full bg-gradient-to-r {{ $barTone }}" style="width: {{ $barWidth }}%"></div>
                    </div>
                    <div class="dale-figures mt-2.5 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Usado <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $money($quota['used']) }}</span></span>
                        <span>Quedan <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $money($quota['left']) }}</span></span>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
