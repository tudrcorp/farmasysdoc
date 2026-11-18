@php
    /** @var list<array<string, mixed>> $cards */
    $toneClasses = [
        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30',
        'info' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-400/30',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30',
        'gray' => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15',
    ];
@endphp

@if ($cards === [])
    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-gray-300 px-6 py-10 text-center dark:border-white/15">
        <x-filament::icon icon="heroicon-o-building-office-2" class="h-8 w-8 text-gray-400" />
        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Ningún aliado tiene este plan.</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Asígnelo desde «Editar», en la sección «Compañías aliadas».</p>
    </div>
@else
    <div class="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(min(100%,24rem),1fr))]">
        @foreach ($cards as $card)
            <article class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900/60 dark:ring-white/10">
                <header class="flex items-start justify-between gap-3 px-5 py-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-sm font-bold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ $card['initials'] }}</span>
                        <div class="min-w-0">
                            @if ($card['url'])
                                <a href="{{ $card['url'] }}" class="block truncate text-base font-bold text-gray-950 hover:text-primary-600 hover:underline dark:text-white dark:hover:text-primary-400">{{ $card['name'] }}</a>
                            @else
                                <p class="truncate text-base font-bold text-gray-950 dark:text-white">{{ $card['name'] }}</p>
                            @endif
                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                @if (filled($card['tax_id']))
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 font-mono font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $card['tax_id'] }}</span>
                                @endif
                                @if ($card['modality'])
                                    <span class="rounded-md px-2 py-0.5 font-medium ring-1 ring-inset {{ $toneClasses[$card['modality']['color']] ?? $toneClasses['gray'] }}">{{ $card['modality']['label'] }}</span>
                                @endif
                                @unless ($card['is_active'])
                                    <span class="rounded-md px-2 py-0.5 font-medium ring-1 ring-inset {{ $toneClasses['danger'] }}">Inactivo</span>
                                @endunless
                            </div>
                        </div>
                    </div>
                    @if ($card['url'])
                        <a href="{{ $card['url'] }}" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                            Ver aliado
                            <x-filament::icon icon="heroicon-m-arrow-up-right" class="h-3.5 w-3.5" />
                        </a>
                    @endif
                </header>

                @if ($card['highlight'] || $card['metrics'] !== [])
                    <div class="grid gap-px border-t border-gray-100 bg-gray-100 sm:grid-cols-[repeat(auto-fit,minmax(8rem,1fr))] dark:border-white/5 dark:bg-white/5">
                        @if ($card['highlight'])
                            <div class="bg-primary-50 px-5 py-4 dark:bg-primary-500/10">
                                <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">{{ $card['highlight']['label'] }}</p>
                                <p class="mt-1 text-2xl font-bold tabular-nums text-primary-700 dark:text-primary-300">{{ $card['highlight']['value'] }}</p>
                                @if (filled($card['highlight']['hint'] ?? null))
                                    <p class="mt-0.5 text-xs tabular-nums text-primary-700/70 dark:text-primary-300/70">{{ $card['highlight']['hint'] }}</p>
                                @endif
                            </div>
                        @endif
                        @foreach ($card['metrics'] as $metric)
                            <div class="bg-white px-5 py-4 dark:bg-gray-900/60">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $metric['value'] }}</p>
                                @if (filled($metric['hint'] ?? null))
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $metric['hint'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($card['profits'] !== null)
                    <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-gray-100 px-5 py-3 dark:border-white/5">
                        <span class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-m-receipt-percent" class="h-3.5 w-3.5" />
                            Ganancias
                        </span>
                        @forelse ($card['profits'] as $profit)
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs ring-1 ring-inset {{ $toneClasses['info'] }}">
                                {{ $profit['label'] }}
                                <span class="font-bold tabular-nums">{{ $profit['value'] }}</span>
                            </span>
                        @empty
                            <span class="text-xs text-gray-400 dark:text-gray-500">No aplican</span>
                        @endforelse
                    </div>
                @endif
            </article>
        @endforeach
    </div>
@endif
