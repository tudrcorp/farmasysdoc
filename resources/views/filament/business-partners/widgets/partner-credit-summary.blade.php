@php
    $money = fn (mixed $value): string => '$'.number_format((float) $value, 2, ',', '.');
@endphp

<x-filament-widgets::widget>
    <div @if ($pollingInterval) wire:poll.{{ $pollingInterval }}.visible @endif>
        @if ($credit)
            @php
                $percent = (int) ($credit['used_percent'] ?? 0);
                $barTone = $percent >= 90 ? 'bg-danger-500' : ($percent >= 75 ? 'bg-warning-500' : 'bg-primary-500');
            @endphp
            <section class="dale-card px-5 py-4" aria-label="Línea de crédito">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disponible</dt>
                        <dd class="dale-kpi-value mt-1 text-3xl font-semibold text-primary-700 dark:text-primary-300">{{ $money($credit['available']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Usado</dt>
                        <dd class="dale-kpi-value mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ $money($credit['consumed']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Línea total</dt>
                        <dd class="dale-kpi-value mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ $money($credit['ceiling']) }}</dd>
                    </div>
                </dl>
                <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Crédito usado">
                    <div class="dale-progress-bar h-full rounded-full {{ $barTone }}" style="width: {{ $percent }}%"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $percent }}% de la línea usada. Se actualiza cada minuto.</p>
            </section>
        @endif
    </div>
</x-filament-widgets::widget>
