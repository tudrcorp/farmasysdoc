@php
    /** @var \App\Support\Partners\AgreementTermStatus $term */
    $chip = [
        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30',
        'info' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-400/30',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30',
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-600/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
    ];
    $tone = [
        'ok' => ['ring' => 'text-success-500', 'text' => 'text-success-700 dark:text-success-400', 'chip' => $chip['success']],
        'warning' => ['ring' => 'text-warning-500', 'text' => 'text-warning-700 dark:text-warning-400', 'chip' => $chip['warning']],
        'danger' => ['ring' => 'text-danger-500', 'text' => 'text-danger-700 dark:text-danger-400', 'chip' => $chip['danger']],
        'pending' => ['ring' => 'text-info-500', 'text' => 'text-info-700 dark:text-info-400', 'chip' => $chip['info']],
        'unknown' => ['ring' => 'text-gray-400', 'text' => 'text-gray-600 dark:text-gray-300', 'chip' => $chip['gray']],
    ][$term->tone] ?? null;
    $tone ??= ['ring' => 'text-gray-400', 'text' => 'text-gray-600 dark:text-gray-300', 'chip' => $chip['gray']];
    $remainingDays = $term->remainingDays === null ? null : max(0, $term->remainingDays);
    $circumference = 2 * M_PI * 52;
    $dash = $remainingPercent === null ? 0 : $circumference * $remainingPercent / 100;
    $plansCount = count($planCards);
    $historyCount = count($history);
@endphp

<x-filament-panels::page>
    <div class="grid gap-6">
        {{-- Convenio vigente --}}
        <section class="dale-card relative overflow-hidden" aria-labelledby="dale-current-agreement">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(42rem_16rem_at_100%_0%,color-mix(in_oklab,var(--primary-500)_14%,transparent),transparent_70%)]"></div>

            <div class="relative grid gap-6 px-5 py-6 sm:px-7 lg:grid-cols-[auto_minmax(0,1fr)] lg:items-center">
                {{-- Anillo de días restantes --}}
                <div class="flex items-center gap-5">
                    <div class="relative h-32 w-32 shrink-0" role="img" aria-label="{{ $remainingDays === null ? $term->label : 'Quedan '.$remainingDays.' días de vigencia' }}">
                        <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90">
                            <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" class="stroke-gray-100 dark:stroke-white/10" />
                            @if ($remainingPercent !== null)
                                <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round" stroke="currentColor"
                                    class="{{ $tone['ring'] }} transition-[stroke-dasharray] duration-700"
                                    stroke-dasharray="{{ round($dash, 2) }} {{ round($circumference, 2) }}" />
                            @endif
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                            @if ($remainingDays !== null)
                                <span class="dale-figures text-3xl font-bold leading-none {{ $tone['text'] }}">{{ number_format($remainingDays, 0, ',', '.') }}</span>
                                <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $remainingDays === 1 ? 'día' : 'días' }}</span>
                            @else
                                <x-filament::icon icon="heroicon-o-calendar" class="h-8 w-8 text-gray-400" />
                            @endif
                        </div>
                    </div>

                    <div class="min-w-0 lg:hidden">
                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $tone['chip'] }}">{{ $term->label }}</span>
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="dale-current-agreement" class="text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl dark:text-white">
                            {{ $current ? 'Convenio '.$current['number'] : 'Su convenio' }}
                        </h2>
                        <span class="hidden rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset lg:inline-flex {{ $tone['chip'] }}">{{ $term->label }}</span>
                        @if ($current)
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $chip['gray'] }}">{{ $current['type'] }}</span>
                        @endif
                    </div>

                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        @if ($term->startsOn && $term->endsOn)
                            Del <span class="font-semibold text-gray-950 dark:text-white">{{ $term->startsOn->translatedFormat('j \d\e F \d\e Y') }}</span>
                            al <span class="font-semibold text-gray-950 dark:text-white">{{ $term->endsOn->translatedFormat('j \d\e F \d\e Y') }}</span>
                            @if ($term->totalDays) <span class="text-gray-500 dark:text-gray-400">· {{ number_format($term->totalDays, 0, ',', '.') }} días</span> @endif
                        @elseif ($term->startsOn)
                            Desde el <span class="font-semibold text-gray-950 dark:text-white">{{ $term->startsOn->translatedFormat('j \d\e F \d\e Y') }}</span> · sin fecha de vencimiento registrada.
                        @else
                            Aún no hay fechas registradas para su convenio.
                        @endif
                    </p>

                    @if ($term->tone === 'warning')
                        <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-warning-700 dark:text-warning-400">
                            <x-filament::icon icon="heroicon-m-bell-alert" class="h-4 w-4" /> Su convenio está por vencer: su ejecutivo de Farmadoc lo contactará para renovarlo.
                        </p>
                    @elseif ($term->tone === 'danger' && $term->remainingDays !== null)
                        <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-danger-700 dark:text-danger-400">
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" /> El convenio venció o vence hoy. Comuníquese con su ejecutivo de Farmadoc.
                        </p>
                    @endif

                    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($conditions as $condition)
                            <div class="flex items-center gap-3 rounded-xl bg-white/70 px-3.5 py-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                    <x-filament::icon :icon="$condition['icon']" class="h-4.5 w-4.5" />
                                </span>
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $condition['label'] }}</dt>
                                    <dd class="dale-figures truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $condition['value'] }}</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>

                    @if ($current && $current['has_contract'])
                        <div class="mt-4">
                            <x-filament::button
                                icon="heroicon-m-arrow-down-tray"
                                wire:click="downloadContract({{ $current['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="downloadContract({{ $current['id'] }})"
                            >
                                Descargar contrato firmado
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Planes e historial en pestañas --}}
        <section x-data="{ tab: 'plans' }" class="grid gap-4">
            <div role="tablist" aria-label="Planes e historial" class="inline-flex w-full gap-1 rounded-xl bg-gray-100 p-1 sm:w-auto sm:justify-self-start dark:bg-white/5">
                @foreach (['plans' => ['Planes contratados', $plansCount, 'heroicon-m-shield-check'], 'history' => ['Historial de convenios', $historyCount, 'heroicon-m-clock']] as $key => [$label, $count, $icon])
                    <button
                        type="button"
                        role="tab"
                        x-on:click="tab = '{{ $key }}'"
                        x-bind:aria-selected="tab === '{{ $key }}'"
                        x-bind:class="tab === '{{ $key }}' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition sm:flex-none"
                    >
                        <x-filament::icon :icon="$icon" class="h-4 w-4" />
                        {{ $label }}
                        <span class="dale-figures rounded-full bg-gray-200 px-2 py-0.5 text-xs dark:bg-white/10">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            <div x-show="tab === 'plans'" role="tabpanel">
                @include('filament.partner-companies.plan-cards', [
                    'cards' => $planCards,
                    'emptyTitle' => 'Su empresa no tiene planes contratados.',
                    'emptyHint' => 'Si espera ver un plan aquí, comuníquese con su ejecutivo de Farmadoc.',
                ])
            </div>

            <div x-show="tab === 'history'" x-cloak role="tabpanel">
                @if ($history === [])
                    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-gray-300 px-6 py-10 text-center dark:border-white/15">
                        <x-filament::icon icon="heroicon-o-document-text" class="h-8 w-8 text-gray-400" />
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Aún no hay convenios aprobados para mostrar.</p>
                    </div>
                @else
                    <div class="dale-card overflow-hidden">
                        <ol class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($history as $item)
                                <li @class([
                                    'flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between',
                                    'bg-primary-50/50 dark:bg-primary-500/5' => $item['is_current'],
                                ])>
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span @class([
                                            'mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                            'bg-primary-600 text-white' => $item['is_current'],
                                            'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => ! $item['is_current'],
                                        ])>
                                            <x-filament::icon :icon="$item['is_current'] ? 'heroicon-m-check' : 'heroicon-m-document-text'" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ $item['number'] }}</span>
                                                <span class="rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $chip[$item['status_color']] ?? $chip['gray'] }}">{{ $item['status'] }}</span>
                                                @if ($item['is_current'])
                                                    <span class="rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $chip['primary'] }}">Vigente</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                {{ $item['type'] }} · <span class="dale-figures">{{ $item['period'] }}</span>@if ($item['days']) <span class="text-gray-500 dark:text-gray-400">· {{ $item['days'] }}</span>@endif
                                            </p>
                                            @if ($item['signed_on'])
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Firmado el {{ $item['signed_on'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($item['has_contract'])
                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-m-arrow-down-tray"
                                            class="sm:shrink-0"
                                            wire:click="downloadContract({{ $item['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="downloadContract({{ $item['id'] }})"
                                        >
                                            Contrato
                                        </x-filament::button>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
