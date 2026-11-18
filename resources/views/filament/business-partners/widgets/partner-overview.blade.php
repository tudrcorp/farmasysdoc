@php
    /** @var array<string, mixed> $metrics */
    $money = fn (mixed $value): string => '$'.number_format((float) $value, 2, ',', '.');
    $compactMoney = function (mixed $value): string {
        $value = (float) $value;

        return match (true) {
            $value >= 1_000_000 => '$'.number_format($value / 1_000_000, 1, ',', '.').' M',
            $value >= 100_000 => '$'.number_format($value / 1_000, 0, ',', '.').' mil',
            default => '$'.number_format($value, 2, ',', '.'),
        };
    };
    $count = fn (mixed $value): string => number_format((int) $value, 0, ',', '.');
    $delta = function (float $current, float $previous): array {
        if ($previous <= 0) {
            return $current > 0
                ? ['text' => 'Nuevo', 'tone' => 'up', 'icon' => 'heroicon-m-arrow-trending-up']
                : ['text' => 'Sin cambios', 'tone' => 'flat', 'icon' => 'heroicon-m-minus'];
        }

        $change = (int) round(($current - $previous) / $previous * 100);

        return match (true) {
            $change > 0 => ['text' => '+'.$change.'%', 'tone' => 'up', 'icon' => 'heroicon-m-arrow-trending-up'],
            $change < 0 => ['text' => $change.'%', 'tone' => 'down', 'icon' => 'heroicon-m-arrow-trending-down'],
            default => ['text' => '0%', 'tone' => 'flat', 'icon' => 'heroicon-m-minus'],
        };
    };
    $deltaClass = [
        'up' => 'bg-success-50 text-success-700 ring-success-600/15 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/20',
        'down' => 'bg-danger-50 text-danger-700 ring-danger-600/15 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/20',
        'flat' => 'bg-gray-100 text-gray-600 ring-gray-600/10 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10',
    ];
    /**
     * Minigráfico: puntos (0–100 × 0–32) y área para una serie.
     *
     * @param  list<float|int>  $values
     * @return array{line: string, area: string, empty: bool}
     */
    $spark = function (array $values): array {
        $values = array_values($values);
        $max = max($values ?: [0]);
        $n = max(count($values) - 1, 1);
        $points = [];
        foreach ($values as $i => $value) {
            $x = round($i / $n * 100, 2);
            $y = $max > 0 ? round(30 - ($value / $max) * 26, 2) : 30;
            $points[] = "{$x},{$y}";
        }

        return [
            'line' => implode(' ', $points),
            'area' => 'M0,32 L'.implode(' L', $points).' L100,32 Z',
            'empty' => $max <= 0,
        ];
    };
    $term = $metrics['term'] ?? null;
    $termTone = $term['tone'] ?? 'unknown';
    $ringTone = ['ok' => 'text-success-500', 'warning' => 'text-warning-500', 'danger' => 'text-danger-500', 'pending' => 'text-info-500'][$termTone] ?? 'text-gray-400';
    $termText = ['ok' => 'text-success-700 dark:text-success-400', 'warning' => 'text-warning-700 dark:text-warning-400', 'danger' => 'text-danger-700 dark:text-danger-400'][$termTone] ?? 'text-gray-950 dark:text-white';
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
    $primaryShortcut = collect($shortcuts)->firstWhere('primary', true);
    $secondaryShortcuts = collect($shortcuts)->where('primary', false)->values();
@endphp

<x-filament-widgets::widget>
    <div @if ($pollingInterval) wire:poll.{{ $pollingInterval }}.visible @endif class="grid gap-5">
        @if ($usesDefaultPassword)
            <div role="status" class="flex flex-col gap-3 rounded-2xl bg-warning-50 px-5 py-3 ring-1 ring-warning-600/20 sm:flex-row sm:items-center sm:justify-between dark:bg-warning-500/10 dark:ring-warning-400/25">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300">
                        <x-filament::icon icon="heroicon-m-key" class="h-4 w-4" />
                    </span>
                    <p class="text-sm text-warning-900 dark:text-warning-100"><span class="font-semibold">Está usando la clave estándar.</span> Le recomendamos cambiarla por una que solo usted conozca.</p>
                </div>
                @if ($profileUrl)
                    <a href="{{ $profileUrl }}" wire:navigate class="inline-flex shrink-0 items-center justify-center rounded-lg bg-warning-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-warning-700">Cambiar clave</a>
                @endif
            </div>
        @endif

        {{-- Cabecera con la marca --}}
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 px-6 py-7 text-white shadow-lg shadow-primary-900/20 sm:px-8 dark:from-primary-700 dark:via-primary-800 dark:to-gray-950">
            <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-white/10 blur-2xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-primary-300/20 blur-3xl"></div>
            <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.07]" xmlns="http://www.w3.org/2000/svg">
                <defs><pattern id="dale-dots" width="22" height="22" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.4" fill="white" /></pattern></defs>
                <rect width="100%" height="100%" fill="url(#dale-dots)" />
            </svg>

            <div class="relative flex flex-col gap-6">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-white/80">{{ $greeting }}{{ filled($firstName) ? ', '.$firstName : '' }} 👋</p>
                    <h1 class="mt-1 text-3xl font-semibold tracking-tight [overflow-wrap:anywhere] sm:text-4xl">{{ $metrics['partner']['name'] ?? 'Su empresa' }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium">
                        @if (filled($metrics['partner']['modality'] ?? null))
                            <span class="rounded-full bg-white/15 px-3 py-1 ring-1 ring-inset ring-white/20 backdrop-blur-sm">{{ $metrics['partner']['modality'] }}</span>
                        @endif
                        @if ($term)
                            @php
                                $termDot = ['ok' => 'bg-emerald-300', 'warning' => 'bg-amber-300', 'danger' => 'bg-rose-300'][$termTone] ?? 'bg-white/70';
                            @endphp
                            @if ($agreementsUrl)
                                <a href="{{ $agreementsUrl }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 ring-1 ring-inset ring-white/20 backdrop-blur-sm hover:bg-white/25">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $termDot }}"></span>
                                    Convenio · {{ $term['label'] }}
                                </a>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 ring-1 ring-inset ring-white/20 backdrop-blur-sm">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $termDot }}"></span>
                                    Convenio · {{ $term['label'] }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($shortcuts !== [])
                    <nav aria-label="Accesos rápidos" class="flex flex-wrap items-stretch gap-2 border-t border-white/15 pt-5">
                        @if ($primaryShortcut)
                            <a href="{{ $primaryShortcut['url'] }}" wire:navigate class="dale-shortcut inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-800 shadow-sm hover:bg-primary-50">
                                <x-filament::icon :icon="$primaryShortcut['icon']" class="h-5 w-5" />
                                {{ $primaryShortcut['label'] }}
                            </a>
                        @endif
                        @foreach ($secondaryShortcuts as $shortcut)
                            <a href="{{ $shortcut['url'] }}" wire:navigate title="{{ $shortcut['description'] }}" class="dale-shortcut inline-flex items-center gap-2 rounded-xl bg-white/10 px-3.5 py-2.5 text-sm font-medium text-white ring-1 ring-inset ring-white/20 backdrop-blur-sm hover:bg-white/20">
                                <x-filament::icon :icon="$shortcut['icon']" class="h-5 w-5 text-white/90" />
                                {{ $shortcut['label'] }}
                            </a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </section>

        @if ($showMetrics)
            @php
                $month = $metrics['month'];
                $series = $metrics['series'];
                $ordersDelta = $delta((float) $month['orders'], (float) $month['previous_orders']);
                $amountDelta = $delta((float) $month['amount'], (float) $month['previous_amount']);
                $ordersSpark = $spark(array_map(fn ($o, $c) => $o + $c, $series['orders'], $series['consumptions'] ?? array_fill(0, count($series['orders']), 0)));
                $amountSpark = $spark(array_map(fn ($o, $c) => $o + $c, $series['amounts'], $series['consumption_amounts'] ?? array_fill(0, count($series['amounts']), 0)));
                $credit = $metrics['credit'];
                $fund = $metrics['fund'];
                $balance = $fund
                    ? ['title' => 'Fondo de consumo disponible', 'left' => $fund['remaining'], 'total' => $fund['authorized'], 'percent' => round(100 - (float) $fund['used_percent'], 2)]
                    : ($credit ? ['title' => 'Crédito disponible', 'left' => $credit['available'], 'total' => $credit['ceiling'], 'percent' => round(100 - (float) ($credit['used_percent'] ?? 0), 2)] : null);
                $circ = 2 * M_PI * 26;
            @endphp

            <section aria-label="Cifras clave" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {{-- Movimientos del mes (pedidos + consumos por convenio) --}}
                <article class="dale-card dale-kpi group relative overflow-hidden p-5">
                    <div class="flex items-start justify-between">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-shopping-bag" class="h-5 w-5" />
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $deltaClass[$ordersDelta['tone']] }}">
                            <x-filament::icon :icon="$ordersDelta['icon']" class="h-3.5 w-3.5" />{{ $ordersDelta['text'] }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">{{ ($month['consumptions'] ?? 0) > 0 ? 'Movimientos del mes' : 'Pedidos del mes' }}</p>
                    <p class="dale-kpi-value mt-1 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $count($month['orders']) }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        @if (($month['consumptions'] ?? 0) > 0)
                            {{ $count($month['order_count'] ?? 0) }} {{ ($month['order_count'] ?? 0) === 1 ? 'pedido' : 'pedidos' }} · {{ $count($month['consumptions']) }} {{ $month['consumptions'] === 1 ? 'consumo' : 'consumos' }}
                        @else
                            Mes anterior: {{ $count($month['previous_orders']) }}
                        @endif
                    </p>
                    <svg viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-12 w-full text-primary-500 {{ $ordersSpark['empty'] ? 'opacity-30' : '' }}">
                        <defs><linearGradient id="dale-spark-orders" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".22" /><stop offset="1" stop-color="currentColor" stop-opacity="0" /></linearGradient></defs>
                        <path d="{{ $ordersSpark['area'] }}" fill="url(#dale-spark-orders)" />
                        <polyline points="{{ $ordersSpark['line'] }}" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                    </svg>
                </article>

                {{-- Monto del mes --}}
                <article class="dale-card dale-kpi group relative overflow-hidden p-5">
                    <div class="flex items-start justify-between">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                            <x-filament::icon icon="heroicon-o-currency-dollar" class="h-5 w-5" />
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $deltaClass[$amountDelta['tone']] }}">
                            <x-filament::icon :icon="$amountDelta['icon']" class="h-3.5 w-3.5" />{{ $amountDelta['text'] }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">Monto del mes</p>
                    <p class="dale-kpi-value mt-1 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white" title="{{ $money($month['amount']) }}">{{ $compactMoney($month['amount']) }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Mes anterior: {{ $money($month['previous_amount']) }}</p>
                    <svg viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-12 w-full text-sky-500 {{ $amountSpark['empty'] ? 'opacity-30' : '' }}">
                        <defs><linearGradient id="dale-spark-amount" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".22" /><stop offset="1" stop-color="currentColor" stop-opacity="0" /></linearGradient></defs>
                        <path d="{{ $amountSpark['area'] }}" fill="url(#dale-spark-amount)" />
                        <polyline points="{{ $amountSpark['line'] }}" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                    </svg>
                </article>

                {{-- Saldo disponible --}}
                <article class="dale-card dale-kpi relative overflow-hidden p-5">
                    @if ($balance)
                        @php
                            $balanceTone = $balance['percent'] <= 10 ? 'text-danger-500' : ($balance['percent'] <= 25 ? 'text-warning-500' : 'text-primary-500');
                        @endphp
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $balance['title'] }}</p>
                                <p class="dale-kpi-value mt-1 text-2xl font-semibold tracking-tight text-gray-950 [overflow-wrap:anywhere] dark:text-white">{{ $money($balance['left']) }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">de {{ $money($balance['total']) }}</p>
                            </div>
                            <div class="relative h-16 w-16 shrink-0" role="img" aria-label="{{ \App\Support\Partners\PartnerPanel::percent($balance['percent']) }} disponible">
                                <svg viewBox="0 0 64 64" class="h-full w-full -rotate-90">
                                    <circle cx="32" cy="32" r="26" fill="none" stroke-width="7" class="stroke-gray-100 dark:stroke-white/10" />
                                    <circle cx="32" cy="32" r="26" fill="none" stroke-width="7" stroke-linecap="round" stroke="currentColor" class="{{ $balanceTone }}" stroke-dasharray="{{ round($circ * max(0, min(100, $balance['percent'])) / 100, 2) }} {{ round($circ, 2) }}" />
                                </svg>
                                <span class="dale-figures absolute inset-0 flex items-center justify-center text-[11px] font-bold text-gray-950 dark:text-white">{{ \App\Support\Partners\PartnerPanel::percent($balance['percent']) }}</span>
                            </div>
                        </div>
                    @else
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                            <x-filament::icon icon="heroicon-o-shield-check" class="h-5 w-5" />
                        </span>
                        <p class="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">Planes contratados</p>
                        <p class="dale-kpi-value mt-1 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $count($metrics['plans_count']) }}</p>
                    @endif
                </article>

                {{-- Vigencia --}}
                <article class="dale-card dale-kpi relative overflow-hidden p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Vigencia del convenio</p>
                            @if ($term && $term['remaining_days'] !== null)
                                <p class="dale-kpi-value mt-1 text-3xl font-semibold tracking-tight {{ $termText }}">{{ $count(max(0, $term['remaining_days'])) }}<span class="ms-1 text-base font-medium text-gray-500 dark:text-gray-400">días</span></p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Vence el {{ $term['ends_on'] }}</p>
                            @else
                                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $term['label'] ?? 'Sin datos' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ filled($term['starts_on'] ?? null) ? 'Desde el '.$term['starts_on'] : '' }}</p>
                            @endif
                        </div>
                        <div class="relative h-16 w-16 shrink-0" aria-hidden="true">
                            <svg viewBox="0 0 64 64" class="h-full w-full -rotate-90">
                                <circle cx="32" cy="32" r="26" fill="none" stroke-width="7" class="stroke-gray-100 dark:stroke-white/10" />
                                @if (($term['progress'] ?? null) !== null)
                                    <circle cx="32" cy="32" r="26" fill="none" stroke-width="7" stroke-linecap="round" stroke="currentColor" class="{{ $ringTone }}" stroke-dasharray="{{ round($circ * (100 - $term['progress']) / 100, 2) }} {{ round($circ, 2) }}" />
                                @endif
                            </svg>
                            <span class="absolute inset-0 flex items-center justify-center text-gray-400">
                                <x-filament::icon icon="heroicon-o-calendar-days" class="h-6 w-6" />
                            </span>
                        </div>
                    </div>
                </article>
            </section>
        @endif
    </div>
</x-filament-widgets::widget>
