@php
    $statusChip = [
        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30',
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-600/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
    ];
@endphp

<div class="text-center">
    <h2 class="text-xl font-semibold tracking-tight text-gray-950 [overflow-wrap:anywhere] dark:text-white">{{ $name }}</h2>
    <p class="mt-0.5 text-sm text-gray-500 [overflow-wrap:anywhere] dark:text-gray-400">{{ $email }}</p>

    <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5 text-xs">
        @if (filled($company))
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-1 font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30">
                <x-filament::icon icon="heroicon-m-building-office-2" class="h-3.5 w-3.5" />{{ $company }}
            </span>
        @endif
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold ring-1 ring-inset {{ $statusChip[$status['color']] ?? $statusChip['gray'] }}">
            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $status['label'] }}
        </span>
        @if ($canManageUsers)
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2.5 py-1 font-semibold text-white">★ Administrador del panel</span>
        @endif
    </div>
</div>

<dl class="mt-5 divide-y divide-gray-100 rounded-xl bg-gray-50 ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-white/5 dark:ring-white/10">
    @foreach ($facts as $fact)
        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
            <dt class="inline-flex items-center gap-2 text-gray-500 dark:text-gray-400">
                <x-filament::icon :icon="$fact['icon']" class="h-4 w-4" />{{ $fact['label'] }}
            </dt>
            <dd class="dale-figures font-medium text-gray-950 dark:text-white">{{ $fact['value'] }}</dd>
        </div>
    @endforeach
</dl>

@if ($usesDefaultPassword)
    <p class="mt-4 flex items-start gap-2 rounded-xl bg-warning-50 px-3 py-2.5 text-left text-xs text-warning-800 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-200 dark:ring-warning-400/25">
        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
        Sigue con la clave estándar. Cámbiela en «Clave de acceso».
    </p>
@endif
