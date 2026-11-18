@php
    $money = fn (mixed $value): string => '$'.number_format((float) $value, 2, ',', '.');
    $signed = fn (float $value): string => ($value >= 0 ? '+' : '−').'$'.number_format(abs($value), 2, ',', '.');
    $tone = ['danger' => 'text-danger-600 dark:text-danger-400', 'info' => 'text-info-600 dark:text-info-400', 'success' => 'text-success-600 dark:text-success-400'];
@endphp

<x-filament-widgets::widget>
    <div @if ($pollingInterval) wire:poll.{{ $pollingInterval }}.visible @endif class="grid gap-5 lg:grid-cols-3">
        <div class="grid content-start gap-4 lg:col-span-2">
            @forelse ($funds as $fund)
                @php
                    $barTone = $fund['percent'] >= 90 ? 'from-danger-500 to-danger-400' : ($fund['percent'] >= 75 ? 'from-warning-500 to-warning-400' : 'from-primary-600 to-primary-400');
                @endphp
                <section class="dale-card p-5 sm:p-6" aria-label="Fondo {{ $fund['plan'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">Fondo de consumo</p>
                            <h2 class="mt-0.5 text-base font-semibold text-gray-950 dark:text-white">{{ $fund['plan'] }}</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($fund['affiliates'], 0, ',', '.') }} afiliados activos</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Disponible</p>
                            <p class="dale-figures text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $money($fund['available']) }}</p>
                        </div>
                    </div>
                    <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ round($fund['percent']) }}" aria-valuemin="0" aria-valuemax="100" aria-label="Fondo usado">
                        <div class="dale-progress-bar h-full rounded-full bg-gradient-to-r {{ $barTone }}" style="width: {{ $fund['percent'] > 0 ? max(1.5, $fund['percent']) : 0 }}%"></div>
                    </div>
                    <dl class="dale-figures mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Autorizado</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $money($fund['authorized']) }}</dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Recargas</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $money($fund['recharges']) }}</dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Consumido</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $money($fund['consumed']) }}</dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Usado</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ \App\Support\Partners\PartnerPanel::percent($fund['percent']) }}</dd></div>
                    </dl>
                </section>
            @empty
                <section class="dale-card p-6 text-center">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Su empresa no tiene un plan Flexible activo.</p>
                </section>
            @endforelse
        </div>

        <section class="dale-card p-5" aria-labelledby="dale-fund-movements">
            <h2 id="dale-fund-movements" class="text-base font-semibold text-gray-950 dark:text-white">Movimientos del fondo</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Los más recientes.</p>
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($movements as $movement)
                    <li class="flex items-start justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $movement['type'] }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $movement['description'] }}">{{ $movement['description'] }}</p>
                            <p class="text-[11px] text-gray-400">{{ $movement['date'] }}</p>
                        </div>
                        <div class="dale-figures shrink-0 text-right">
                            <p class="text-sm font-semibold {{ $tone[$movement['color']] ?? '' }}">{{ $signed($movement['amount']) }}</p>
                            <p class="text-[11px] text-gray-400">Saldo {{ $money($movement['balance']) }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin movimientos todavía.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-filament-widgets::widget>
