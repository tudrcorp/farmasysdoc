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
    $isMonthToDate = (bool) ($is_month_to_date ?? true);
    $goalIsProrated = (bool) ($goal_is_prorated ?? false);
    $periodNoun = $isMonthToDate ? 'mes' : 'período';
    $bcvFootnote = $total_ves > 0
        ? 'BCV efectivo del '.$periodNoun
        : 'BCV referencia';
    $isBranchScoped = ($selected_branch_id ?? null) !== null;
    $branchGoalUsd = $branch_goal_usd ?? null;
    $branchGoalSharePercent = $branch_goal_share_percent ?? null;
    $branchGoalProgressPercent = $branch_goal_progress_percent ?? null;
    $branchShareMarkerLeft = $hasGoal && $branchGoalSharePercent !== null
        ? min(100, max(0, $branchGoalSharePercent))
        : null;
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
                Ventas del {{ $periodNoun }} · meta global
            </h2>
            <p class="fi-farmaadmin-branch-monthly-stats__description">
                {{ $month_label }} · {{ $scope_description }}
            </p>
        </header>

        <div class="fi-farmaadmin-branch-monthly-stats__grid fi-farmaadmin-branch-monthly-stats__grid--single">
            <article class="fi-farmaadmin-branch-monthly-stat fi-farmaadmin-branch-monthly-stat--variant-0">
                <header class="fi-farmaadmin-branch-monthly-stat__head">
                    <span class="fi-farmaadmin-branch-monthly-stat__icon" aria-hidden="true">
                        <x-filament::icon
                            :icon="$isBranchScoped ? 'heroicon-o-building-storefront' : 'heroicon-o-globe-alt'"
                            class="size-4"
                        />
                    </span>
                    <div class="fi-farmaadmin-branch-monthly-stat__identity">
                        <h3 class="fi-farmaadmin-branch-monthly-stat__name" title="Meta global">
                            @if ($isBranchScoped)
                                Aporte de {{ $selected_branch_name }}
                            @else
                                Total del {{ $periodNoun }}
                            @endif
                        </h3>
                    </div>
                    <span class="fi-farmaadmin-branch-monthly-stat__totals">
                        <span class="fi-farmaadmin-branch-monthly-stat__total">
                            {{ Number::currency($total_usd, 'USD', 'en', 2) }}
                        </span>
                        <span class="fi-farmaadmin-branch-monthly-stat__total fi-farmaadmin-branch-monthly-stat__total--ves">
                            Bs. {{ number_format($total_ves, 2, ',', '.') }}
                        </span>
                    </span>
                </header>

                <div class="fi-farmaadmin-branch-monthly-stat__goal">
                    <div class="fi-farmaadmin-branch-monthly-stat__goal-meta">
                        @if ($hasGoal && $goalUsd !== null && $progressPercent !== null)
                            <span class="fi-farmaadmin-branch-monthly-stat__goal-label">
                                {{ $goalIsProrated ? 'Meta global prorrateada' : 'Meta global' }} {{ Number::currency($goalUsd, 'USD', 'en', 0) }}
                            </span>
                            <span class="fi-farmaadmin-branch-monthly-stat__goal-percent">
                                {{ number_format($progressPercent, 1, ',', '.') }}%
                                @if ($isBranchScoped)
                                    de la meta global
                                @endif
                            </span>
                        @else
                            <span class="fi-farmaadmin-branch-monthly-stat__goal-label fi-farmaadmin-branch-monthly-stat__goal-label--empty">
                                Sin meta global en el {{ $periodNoun }}
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
                            aria-label="{{ $isBranchScoped ? 'Aporte de la sucursal a la meta global del '.$periodNoun : 'Progreso de ventas respecto a la meta global del '.$periodNoun }}"
                        @else
                            aria-valuenow="0"
                            aria-label="Sin meta global configurada para el {{ $periodNoun }}"
                        @endif
                    >
                        <div
                            class="fi-farmaadmin-branch-monthly-stat__progress-fill"
                            style="width: {{ number_format($progressBarWidth, 2, '.', '') }}%"
                        ></div>

                        @if ($isBranchScoped && $branchShareMarkerLeft !== null)
                            <span
                                class="fi-farmaadmin-branch-monthly-stat__progress-marker"
                                style="left: {{ number_format($branchShareMarkerLeft, 2, '.', '') }}%"
                                title="Porción de la meta global asignada a la sucursal"
                                aria-hidden="true"
                            ></span>
                        @endif
                    </div>

                    @if ($isBranchScoped && $hasGoal)
                        <p class="fi-farmaadmin-branch-monthly-stat__goal-context">
                            @if ($branchGoalUsd !== null && $branchGoalSharePercent !== null)
                                <span class="fi-farmaadmin-branch-monthly-stat__goal-context-marker" aria-hidden="true"></span>
                                Meta de la sucursal {{ Number::currency($branchGoalUsd, 'USD', 'en', 0) }}
                                = {{ number_format($branchGoalSharePercent, 1, ',', '.') }}% de la meta global
                                @if ($branchGoalProgressPercent !== null)
                                    · lleva {{ number_format($branchGoalProgressPercent, 1, ',', '.') }}% de su meta
                                @endif
                            @else
                                Sucursal sin meta asignada en el {{ $periodNoun }}
                            @endif
                        </p>
                    @endif
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
