@php
    use Illuminate\Support\Number;

    $bcvRate = $bcv_rate_used;
    $bcvLabel = $bcvRate !== null
        ? 'Bs. ' . number_format($bcvRate, 2, ',', '.') . '/USD'
        : '—';
    $hasGoal = (bool) ($has_goal ?? false);
    $goalUsd = $goal_usd ?? null;
    $progressPercent = $goal_progress_percent ?? null;
    $progressBarWidth = $hasGoal && $progressPercent !== null
        ? min(100, max(0, $progressPercent))
        : 0;
    $bcvFootnote = $total_ves > 0
        ? 'BCV efectivo del mes'
        : 'BCV referencia';
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class([
                'fi-wi-widget',
                'fi-farmaadmin-stats-all-branches-ios',
            ])
    "
>
    <section class="fi-farmaadmin-branch-monthly-stats">
        <header class="fi-farmaadmin-branch-monthly-stats__header">
            <h2 class="fi-farmaadmin-branch-monthly-stats__title">
                Ventas del mes · meta global
            </h2>
            <p class="fi-farmaadmin-branch-monthly-stats__description">
                {{ $month_label }} · {{ $scope_description }}
            </p>
        </header>

        <div class="fi-farmaadmin-branch-monthly-stats__grid fi-farmaadmin-branch-monthly-stats__grid--single">
            <article class="fi-farmaadmin-branch-monthly-stat fi-farmaadmin-branch-monthly-stat--variant-0">
                <header class="fi-farmaadmin-branch-monthly-stat__head">
                    <span class="fi-farmaadmin-branch-monthly-stat__icon" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-globe-alt" class="size-4" />
                    </span>
                    <div class="fi-farmaadmin-branch-monthly-stat__identity">
                        <h3 class="fi-farmaadmin-branch-monthly-stat__name" title="Meta global">
                            Total del mes
                        </h3>
                    </div>
                    <span class="fi-farmaadmin-branch-monthly-stat__total">
                        {{ Number::currency($general_total_usd, 'USD', 'en', 2) }}
                    </span>
                </header>

                <div class="fi-farmaadmin-branch-monthly-stat__goal">
                    <div class="fi-farmaadmin-branch-monthly-stat__goal-meta">
                        @if ($hasGoal && $goalUsd !== null && $progressPercent !== null)
                            <span class="fi-farmaadmin-branch-monthly-stat__goal-label">
                                Meta global {{ Number::currency($goalUsd, 'USD', 'en', 0) }}
                            </span>
                            <span class="fi-farmaadmin-branch-monthly-stat__goal-percent">
                                {{ number_format($progressPercent, 1, ',', '.') }}%
                            </span>
                        @else
                            <span class="fi-farmaadmin-branch-monthly-stat__goal-label fi-farmaadmin-branch-monthly-stat__goal-label--empty">
                                Sin meta global del mes
                            </span>
                            <span class="fi-farmaadmin-branch-monthly-stat__goal-percent">—</span>
                        @endif
                    </div>

                    <div
                        class="fi-farmaadmin-branch-monthly-stat__progress-track"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        @if ($hasGoal && $progressPercent !== null)
                            aria-valuenow="{{ number_format($progressBarWidth, 1, '.', '') }}"
                            aria-label="Progreso de ventas respecto a la meta global del mes"
                        @else
                            aria-valuenow="0"
                            aria-label="Sin meta global configurada para el mes"
                        @endif
                    >
                        <div
                            class="fi-farmaadmin-branch-monthly-stat__progress-fill"
                            style="width: {{ number_format($progressBarWidth, 2, '.', '') }}%"
                        ></div>
                    </div>
                </div>

                <dl class="fi-farmaadmin-branch-monthly-stat__metrics">
                    <div class="fi-farmaadmin-branch-monthly-stat__metric">
                        <dt>USD</dt>
                        <dd>{{ Number::currency($total_usd, 'USD', 'en', 2) }}</dd>
                    </div>
                    <div class="fi-farmaadmin-branch-monthly-stat__metric">
                        <dt>Bs.</dt>
                        <dd>{{ number_format($total_ves, 0, ',', '.') }}</dd>
                    </div>
                    <div class="fi-farmaadmin-branch-monthly-stat__metric">
                        <dt>BCV</dt>
                        <dd>{{ $bcvLabel }}</dd>
                    </div>
                    <div class="fi-farmaadmin-branch-monthly-stat__metric fi-farmaadmin-branch-monthly-stat__metric--note">
                        <dt>{{ $bcvFootnote }}</dt>
                        <dd>{{ $month_label }}</dd>
                    </div>
                </dl>
            </article>
        </div>
    </section>
</x-filament-widgets::widget>
