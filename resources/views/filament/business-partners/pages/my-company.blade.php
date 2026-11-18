@php
    $chip = [
        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30',
        'info' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-400/30',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30',
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-600/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
    ];
    $toneText = [
        'ok' => 'text-success-700 dark:text-success-400',
        'warning' => 'text-warning-700 dark:text-warning-400',
        'danger' => 'text-danger-700 dark:text-danger-400',
        'pending' => 'text-info-700 dark:text-info-400',
    ];
@endphp

<x-filament-panels::page>
    <div class="grid gap-6">
        {{-- Identidad de la empresa --}}
        <section class="dale-card relative overflow-hidden" aria-labelledby="dale-company-name">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(42rem_16rem_at_0%_0%,color-mix(in_oklab,var(--primary-500)_16%,transparent),transparent_70%)]"></div>

            <div class="relative flex flex-col gap-5 px-5 py-6 sm:flex-row sm:items-center sm:px-7">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary-600 text-xl font-bold text-white shadow-sm sm:h-20 sm:w-20 sm:text-2xl">{{ $company['initials'] }}</span>

                <div class="min-w-0 flex-1">
                    <h2 id="dale-company-name" class="truncate text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl dark:text-white">{{ $company['legal_name'] }}</h2>
                    @if ($company['trade_name'])
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Nombre comercial: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $company['trade_name'] }}</span></p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        @if (filled($company['tax_id']))
                            <span x-data="{ copied: false }" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 font-medium text-gray-700 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                                RIF <span class="dale-figures font-mono font-semibold text-gray-950 dark:text-white">{{ $company['tax_id'] }}</span>
                                <button type="button" class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400" x-on:click="navigator.clipboard?.writeText(@js($company['tax_id'])); copied = true; setTimeout(() => copied = false, 1500)" x-bind:aria-label="copied ? 'Copiado' : 'Copiar RIF'">
                                    <x-filament::icon x-show="! copied" icon="heroicon-m-clipboard-document" class="h-3.5 w-3.5" />
                                    <x-filament::icon x-show="copied" x-cloak icon="heroicon-m-check" class="h-3.5 w-3.5 text-success-600" />
                                </button>
                            </span>
                        @endif
                        @if (filled($company['code']))
                            <span class="rounded-lg px-2.5 py-1 font-mono font-medium ring-1 ring-inset {{ $chip['gray'] }}">{{ $company['code'] }}</span>
                        @endif
                        @if ($company['modality'])
                            <span class="rounded-lg px-2.5 py-1 font-semibold ring-1 ring-inset {{ $chip[$company['modality']['color']] ?? $chip['gray'] }}">{{ $company['modality']['label'] }}</span>
                        @endif
                        @if (filled($company['client_type']))
                            <span class="rounded-lg px-2.5 py-1 font-medium ring-1 ring-inset {{ $chip['gray'] }}">{{ $company['client_type'] }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 font-semibold ring-1 ring-inset {{ $company['is_active'] ? $chip['success'] : $chip['danger'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $company['is_active'] ? 'bg-success-500' : 'bg-danger-500' }}"></span>
                            {{ $company['is_active'] ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>
                </div>
            </div>

            <dl class="relative grid grid-cols-2 gap-px border-t border-gray-100 bg-gray-100 lg:grid-cols-4 dark:border-white/5 dark:bg-white/5">
                @foreach ($facts as $fact)
                    <div class="flex items-center gap-3 bg-white px-5 py-4 dark:bg-gray-900">
                        <x-filament::icon :icon="$fact['icon']" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                        <div class="min-w-0">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $fact['label'] }}</dt>
                            <dd class="dale-figures truncate text-sm font-semibold {{ $toneText[$fact['tone'] ?? ''] ?? 'text-gray-950 dark:text-white' }}">{{ $fact['value'] }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Contacto --}}
            <section class="dale-card px-5 py-5 lg:col-span-1" aria-labelledby="dale-contact">
                <h2 id="dale-contact" class="text-base font-semibold text-gray-950 dark:text-white">Contacto de la empresa</h2>
                <ul class="mt-3 space-y-1">
                    @forelse ($contact as $item)
                        <li>
                            <a href="{{ $item['href'] }}" @if ($item['external'] ?? false) target="_blank" rel="noopener noreferrer" @endif class="dale-row-link -mx-2 flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-gray-50 dark:hover:bg-white/5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                    <x-filament::icon :icon="$item['icon']" class="h-4.5 w-4.5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
                                    <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $item['value'] }}</span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-gray-400">Sin datos de contacto registrados.</li>
                    @endforelse
                    @if ($address)
                        <li class="-mx-2 flex items-start gap-3 px-2 py-2">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                <x-filament::icon icon="heroicon-o-map-pin" class="h-4.5 w-4.5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs text-gray-500 dark:text-gray-400">Dirección</span>
                                <span class="block text-sm font-medium text-gray-950 dark:text-white">{{ $address }}</span>
                            </span>
                        </li>
                    @endif
                </ul>
            </section>

            {{-- Responsables --}}
            <section class="dale-card px-5 py-5 lg:col-span-2" aria-labelledby="dale-people">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="dale-people" class="text-base font-semibold text-gray-950 dark:text-white">Responsables del convenio</h2>
                    @if ($people !== [])
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($people) === 1 ? '1 persona' : count($people).' personas' }}</span>
                    @endif
                </div>

                @if ($people === [])
                    <div class="mt-3 flex flex-col items-center gap-2 rounded-xl border border-dashed border-gray-300 px-6 py-8 text-center dark:border-white/15">
                        <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8 text-gray-400" />
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">No hay responsables registrados.</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Indíquele a su ejecutivo de Farmadoc quiénes son las personas de contacto de su empresa.</p>
                    </div>
                @else
                    <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($people as $person)
                            <li class="flex gap-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ $person['initials'] }}</span>
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-950 dark:text-white">
                                        <span class="truncate">{{ $person['name'] }}</span>
                                        @if ($person['is_responsible'])
                                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $chip['primary'] }}">Principal</span>
                                        @endif
                                    </p>
                                    @if (filled($person['position']))
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $person['position'] }}</p>
                                    @endif
                                    <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                        @if (filled($person['email']))
                                            <a href="mailto:{{ $person['email'] }}" class="inline-flex items-center gap-1 text-primary-700 hover:underline dark:text-primary-400">
                                                <x-filament::icon icon="heroicon-m-envelope" class="h-3.5 w-3.5" />{{ $person['email'] }}
                                            </a>
                                        @endif
                                        @if (filled($person['phone']))
                                            <a href="tel:{{ $person['phone'] }}" class="inline-flex items-center gap-1 text-primary-700 hover:underline dark:text-primary-400">
                                                <x-filament::icon icon="heroicon-m-phone" class="h-3.5 w-3.5" />{{ $person['phone'] }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Sucursales --}}
        <section class="dale-card px-5 py-5" aria-labelledby="dale-branches">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h2 id="dale-branches" class="text-base font-semibold text-gray-950 dark:text-white">Sucursales de Farmadoc</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Donde se atiende su convenio.</p>
                </div>
                @if ($branches !== [])
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($branches) === 1 ? '1 sucursal' : count($branches).' sucursales' }}</span>
                @endif
            </div>

            @if ($branches === [])
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Sin sucursales asignadas.</p>
            @else
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($branches as $branch)
                        <li class="flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                <x-filament::icon icon="heroicon-o-building-storefront" class="h-4.5 w-4.5" />
                            </span>
                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $branch }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <p class="text-center text-xs text-gray-500 dark:text-gray-400">¿Algún dato no está correcto? Comuníquese con su ejecutivo de Farmadoc para actualizarlo.</p>
    </div>
</x-filament-panels::page>
