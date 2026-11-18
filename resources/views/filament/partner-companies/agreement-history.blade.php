@php
    /** @var list<array<string, mixed>> $items */
    $chip = [
        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30',
        'info' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-400/30',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30',
        'gray' => 'bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15',
    ];
@endphp

@if ($items === [])
    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-gray-300 px-6 py-8 text-center dark:border-white/15">
        <x-filament::icon icon="heroicon-o-document-text" class="h-8 w-8 text-gray-400" />
        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Aún no hay convenios registrados.</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Indique la fecha de creación y de vencimiento del convenio en «Editar»: al guardar se registra el convenio inicial en borrador.</p>
    </div>
@else
    <ol class="relative ms-3 border-s-2 border-gray-200 dark:border-white/10">
        @foreach ($items as $item)
            <li class="relative pb-5 ps-6 last:pb-0">
                <span @class([
                    'absolute -start-[9px] top-1.5 h-4 w-4 rounded-full ring-4 ring-white dark:ring-gray-900',
                    'bg-success-500' => $item['is_current'],
                    'bg-gray-300 dark:bg-gray-600' => ! $item['is_current'],
                ])></span>
                <div @class([
                    'rounded-xl px-4 py-3 ring-1',
                    'bg-success-50/60 ring-success-600/20 dark:bg-success-500/5 dark:ring-success-400/20' => $item['is_current'],
                    'bg-white ring-gray-950/5 dark:bg-gray-900/60 dark:ring-white/10' => ! $item['is_current'],
                ])>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" class="font-mono text-sm font-bold text-gray-950 hover:text-primary-600 hover:underline dark:text-white dark:hover:text-primary-400">{{ $item['number'] }}</a>
                            @else
                                <span class="font-mono text-sm font-bold text-gray-950 dark:text-white">{{ $item['number'] }}</span>
                            @endif
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $chip[$item['type_color']] ?? $chip['gray'] }}">{{ $item['type'] }}</span>
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $chip[$item['status_color']] ?? $chip['gray'] }}">{{ $item['status'] }}</span>
                            @if ($item['is_current'])
                                <span class="rounded-md bg-success-600 px-2 py-0.5 text-xs font-semibold text-white">Vigente</span>
                            @endif
                            @if ($item['is_automatic'])
                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">Renovación automática</span>
                            @endif
                        </div>
                        <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $item['days'] }}</span>
                    </div>
                    <p class="mt-1.5 text-sm text-gray-700 dark:text-gray-200">
                        {{ $item['period'] }}
                        @if ($item['term_label'])
                            <span class="text-gray-400">·</span>
                            <span class="font-medium">{{ $item['term_label'] }}</span>
                        @endif
                    </p>
                    @if (filled($item['reason']))
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Motivo: {{ $item['reason'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
