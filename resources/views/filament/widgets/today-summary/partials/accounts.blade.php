{{-- Tarjeta compartida de CxP / CxC del "Resumen de hoy". --}}
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
                {{ $title }}
            </h2>
            <p class="fi-farmaadmin-branch-monthly-stats__description">
                Saldo al día de hoy · movimientos de {{ $periodLabel }}
                @if (filled($branchLabel))
                    · {{ $branchLabel }}
                @endif
            </p>
        </header>

        <article class="fi-farmaadmin-today-summary__kpi fi-farmaadmin-today-summary__kpi--primary fi-farmaadmin-today-summary__kpi--{{ $tone }}">
            <span class="fi-farmaadmin-today-summary__kpi-label">{{ $pendingLabel }}</span>
            <span class="fi-farmaadmin-today-summary__kpi-value">{{ Number::currency($summary['pending_usd'], 'USD', 'en', 2) }}</span>
            <span class="fi-farmaadmin-today-summary__kpi-hint">
                Bs. {{ number_format($summary['pending_ves'], 2, ',', '.') }}
                · {{ $summary['pending_count'] }} {{ $summary['pending_count'] === 1 ? $documentSingular : $documentPlural }}
            </span>
        </article>

        <dl class="fi-farmaadmin-today-summary__metrics">
            <div @class([
                'fi-farmaadmin-today-summary__metric',
                'fi-farmaadmin-today-summary__metric--danger' => $summary['overdue_count'] > 0,
            ])>
                <dt>Vencido</dt>
                <dd>{{ Number::currency($summary['overdue_usd'], 'USD', 'en', 2) }}</dd>
                <dd class="fi-farmaadmin-today-summary__metric-hint">{{ $summary['overdue_count'] }} {{ $summary['overdue_count'] === 1 ? $documentSingular : $documentPlural }}</dd>
            </div>
            <div @class([
                'fi-farmaadmin-today-summary__metric',
                'fi-farmaadmin-today-summary__metric--warning' => $summary['due_soon_count'] > 0,
            ])>
                <dt>Vence en 7 días</dt>
                <dd>{{ Number::currency($summary['due_soon_usd'], 'USD', 'en', 2) }}</dd>
                <dd class="fi-farmaadmin-today-summary__metric-hint">{{ $summary['due_soon_count'] }} {{ $summary['due_soon_count'] === 1 ? $documentSingular : $documentPlural }}</dd>
            </div>
            <div class="fi-farmaadmin-today-summary__metric">
                <dt>Emitido en el período</dt>
                <dd>{{ Number::currency($summary['issued_usd'], 'USD', 'en', 2) }}</dd>
                <dd class="fi-farmaadmin-today-summary__metric-hint">{{ $summary['issued_count'] }} {{ $summary['issued_count'] === 1 ? $documentSingular : $documentPlural }}</dd>
            </div>
            @isset($summary['settled_usd'])
                <div class="fi-farmaadmin-today-summary__metric fi-farmaadmin-today-summary__metric--success">
                    <dt>{{ $settledLabel }}</dt>
                    <dd>{{ Number::currency($summary['settled_usd'], 'USD', 'en', 2) }}</dd>
                    <dd class="fi-farmaadmin-today-summary__metric-hint">{{ $summary['settled_count'] }} {{ $summary['settled_count'] === 1 ? $documentSingular : $documentPlural }}</dd>
                </div>
            @endisset
        </dl>

        <div class="fi-farmaadmin-today-summary__overdue">
            <p class="fi-farmaadmin-today-summary__overdue-title">Más atrasadas</p>

            @forelse ($summary['overdue_items'] as $item)
                @php
                    $tag = filled($item['url']) ? 'a' : 'div';
                @endphp
                <{{ $tag }}
                    @if (filled($item['url'])) href="{{ $item['url'] }}" wire:navigate @endif
                    class="fi-farmaadmin-today-summary__overdue-item"
                >
                    <span class="fi-farmaadmin-today-summary__overdue-main">
                        <span class="fi-farmaadmin-today-summary__overdue-name">{{ $item['name'] }}</span>
                        <span class="fi-farmaadmin-today-summary__overdue-meta">
                            @if (filled($item['document']))
                                {{ $item['document'] }} ·
                            @endif
                            Venció {{ $item['due_at'] }}
                        </span>
                    </span>
                    <span class="fi-farmaadmin-today-summary__overdue-side">
                        <span class="fi-farmaadmin-today-summary__overdue-amount">{{ Number::currency($item['amount_usd'], 'USD', 'en', 2) }}</span>
                        <span class="fi-farmaadmin-today-summary__overdue-days">
                            {{ $item['days_overdue'] }} {{ $item['days_overdue'] === 1 ? 'día' : 'días' }}
                        </span>
                    </span>
                </{{ $tag }}>
            @empty
                <p class="fi-farmaadmin-today-summary__overdue-empty">
                    <x-filament::icon icon="heroicon-o-check-circle" class="size-4" />
                    {{ $emptyOverdueLabel }}
                </p>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>
