@php
    /** @var list<array<string, mixed>> $cards */
    $statusClasses = [
        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30',
        'gray' => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15',
    ];
@endphp

@if ($cards === [])
    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-gray-300 px-6 py-10 text-center dark:border-white/15">
        <x-filament::icon icon="heroicon-o-shield-check" class="h-8 w-8 text-gray-400" />
        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $emptyTitle ?? 'Este aliado no tiene planes asignados.' }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $emptyHint ?? 'Asígnelos desde la ficha de cada plan, en «Compañías aliadas».' }}</p>
    </div>
@else
    <div @class(['grid gap-4', 'xl:grid-cols-2' => count($cards) > 1])>
        @foreach ($cards as $card)
            <article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900/60 dark:ring-white/10">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-white/5">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-s-shield-check" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            @if ($card['url'])
                                <a href="{{ $card['url'] }}" class="text-base font-bold text-gray-950 hover:text-primary-600 hover:underline dark:text-white dark:hover:text-primary-400">{{ $card['name'] }}</a>
                            @else
                                <p class="text-base font-bold text-gray-950 dark:text-white">{{ $card['name'] }}</p>
                            @endif
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
                                <span class="rounded-md bg-gray-100 px-2 py-0.5 font-mono font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $card['code'] }}</span>
                                <span class="rounded-md px-2 py-0.5 font-medium ring-1 ring-inset {{ $statusClasses[$card['status']['color']] ?? $statusClasses['gray'] }}">{{ $card['status']['label'] }}</span>
                                <span class="rounded-md bg-primary-50 px-2 py-0.5 font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30">{{ $card['mode'] }}</span>
                            </div>
                        </div>
                    </div>
                    @if ($card['url'])
                        <a href="{{ $card['url'] }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                            Ver plan
                            <x-filament::icon icon="heroicon-m-arrow-up-right" class="h-3.5 w-3.5" />
                        </a>
                    @endif
                </header>

                <div class="grid gap-px bg-gray-100 sm:grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] dark:bg-white/5">
                    @foreach ($card['metrics'] as $metric)
                        <div class="bg-white px-5 py-4 dark:bg-gray-900/60">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                            <p class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $metric['value'] }}</p>
                            @if (filled($metric['hint'] ?? null))
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $metric['hint'] }}</p>
                            @endif
                        </div>
                    @endforeach
                    @if ($card['highlight'])
                        <div class="bg-primary-50 px-5 py-4 dark:bg-primary-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">{{ $card['highlight']['label'] }}</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-primary-700 dark:text-primary-300">{{ $card['highlight']['value'] }}</p>
                            @if (filled($card['highlight']['hint'] ?? null))
                                <p class="mt-0.5 text-xs text-primary-700/70 dark:text-primary-300/70">{{ $card['highlight']['hint'] }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
                    @foreach (['services' => ['Servicios', 'Sin servicios', 'heroicon-m-squares-2x2'], 'formularies' => ['Catálogos', 'Sin catálogos', 'heroicon-m-book-open']] as $key => [$title, $empty, $icon])
                        <div>
                            <p class="mb-2 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <x-filament::icon :icon="$icon" class="h-3.5 w-3.5" />
                                {{ $title }}
                            </p>
                            @if ($card[$key] === [])
                                <p class="text-sm text-gray-400 dark:text-gray-500">{{ $empty }}</p>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($card[$key] as $name)
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-200">{{ $name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($card['inherits'])
                    <p class="border-t border-gray-100 px-5 py-2.5 text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">Incluye los servicios y catálogos del plan base más los del subplan.</p>
                @endif
            </article>
        @endforeach
    </div>
@endif
