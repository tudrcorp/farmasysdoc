@php
    use Illuminate\Support\Number;
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class([
                'fi-wi-widget',
                'fi-farmaadmin-stats-all-branches-ios',
                'fi-farmaadmin-today-summary',
            ])
    "
>
    <section class="fi-farmaadmin-branch-monthly-stats">
        <header class="fi-farmaadmin-branch-monthly-stats__header">
            <h2 class="fi-farmaadmin-branch-monthly-stats__title">
                Ventas · {{ $periodLabel }}
            </h2>
            <p class="fi-farmaadmin-branch-monthly-stats__description">
                {{ number_format($summary['operations_count'], 0, ',', '.') }}
                {{ $summary['operations_count'] === 1 ? 'operación' : 'operaciones' }}
                · {{ filled($branchLabel) ? $branchLabel : 'todas las sucursales visibles' }}
            </p>
        </header>

        <div class="fi-farmaadmin-today-summary__kpis">
            <article class="fi-farmaadmin-today-summary__kpi fi-farmaadmin-today-summary__kpi--primary">
                <span class="fi-farmaadmin-today-summary__kpi-label">Total vendido</span>
                <span class="fi-farmaadmin-today-summary__kpi-value">{{ Number::currency($summary['general_total_usd'], 'USD', 'en', 2) }}</span>
                <span class="fi-farmaadmin-today-summary__kpi-hint">Total general de ventas completadas</span>
            </article>
            <article class="fi-farmaadmin-today-summary__kpi">
                <span class="fi-farmaadmin-today-summary__kpi-label">Operaciones</span>
                <span class="fi-farmaadmin-today-summary__kpi-value">{{ number_format($summary['operations_count'], 0, ',', '.') }}</span>
                <span class="fi-farmaadmin-today-summary__kpi-hint">Ticket promedio {{ Number::currency($summary['average_ticket_usd'], 'USD', 'en', 2) }}</span>
            </article>
            <article class="fi-farmaadmin-today-summary__kpi">
                <span class="fi-farmaadmin-today-summary__kpi-label">Cobrado en USD</span>
                <span class="fi-farmaadmin-today-summary__kpi-value">{{ Number::currency($summary['collected_usd'], 'USD', 'en', 2) }}</span>
                <span class="fi-farmaadmin-today-summary__kpi-hint">Divisas recibidas</span>
            </article>
            <article class="fi-farmaadmin-today-summary__kpi">
                <span class="fi-farmaadmin-today-summary__kpi-label">Cobrado en Bs.</span>
                <span class="fi-farmaadmin-today-summary__kpi-value">{{ number_format($summary['collected_ves'], 2, ',', '.') }}</span>
                <span class="fi-farmaadmin-today-summary__kpi-hint">Bolívares recibidos</span>
            </article>
        </div>

        @if (count($summary['branches']) > 1)
            <div class="fi-farmaadmin-today-summary__branches">
                @foreach ($summary['branches'] as $index => $branch)
                    @php
                        $share = $summary['general_total_usd'] > 0
                            ? min(100, ($branch['general_total_usd'] / $summary['general_total_usd']) * 100)
                            : 0;
                    @endphp
                    <article class="fi-farmaadmin-today-summary__branch" style="animation-delay: {{ $index * 0.04 }}s">
                        <div class="fi-farmaadmin-today-summary__branch-head">
                            <span class="fi-farmaadmin-today-summary__branch-name" title="{{ $branch['branch_name'] }}">
                                <x-filament::icon icon="heroicon-o-building-storefront" class="size-4" />
                                {{ $branch['branch_name'] }}
                            </span>
                            <span class="fi-farmaadmin-today-summary__branch-total">
                                {{ Number::currency($branch['general_total_usd'], 'USD', 'en', 2) }}
                            </span>
                        </div>
                        <div class="fi-farmaadmin-today-summary__bar" aria-hidden="true">
                            <span style="width: {{ number_format($share, 2, '.', '') }}%"></span>
                        </div>
                        <p class="fi-farmaadmin-today-summary__branch-meta">
                            {{ number_format($branch['operations_count'], 0, ',', '.') }}
                            {{ $branch['operations_count'] === 1 ? 'operación' : 'operaciones' }}
                            · {{ number_format($share, 1, ',', '.') }}% del total
                        </p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-filament-widgets::widget>
