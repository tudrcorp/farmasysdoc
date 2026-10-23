@php
    use Illuminate\Support\Number;

    $goalUsd = $progress['goal_usd'];
    $hasGoal = $goalUsd !== null;
    $branchPercent = $progress['branch_progress_percent'];
    $cashierGoalPercent = $progress['cashier_goal_share_percent'];
    $branchBarWidth = $hasGoal && $branchPercent !== null ? min(100, max(0, $branchPercent)) : 0;
    $cashierBarWidth = $hasGoal && $cashierGoalPercent !== null ? min($branchBarWidth, max(0, $cashierGoalPercent)) : 0;
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class([
                'fi-wi-widget',
                'fi-farmaadmin-stats-all-branches-ios',
                'fi-farmaadmin-today-summary',
                'fi-farmaadmin-cashier-goal',
            ])
    "
>
    <section class="fi-farmaadmin-branch-monthly-stats" wire:poll.{{ $pollingInterval }}.visible>
        <header class="fi-farmaadmin-branch-monthly-stats__header">
            <h2 class="fi-farmaadmin-branch-monthly-stats__title">
                Meta de {{ $progress['branch_name'] ?? 'tu sucursal' }}
            </h2>
            <p class="fi-farmaadmin-branch-monthly-stats__description">
                {{ $progress['month_label'] }}
                <span class="fi-farmaadmin-cashier-goal__live">
                    <span class="fi-farmaadmin-cashier-goal__live-dot" aria-hidden="true"></span>
                    En vivo
                </span>
            </p>
        </header>

        @if ($progress['branch_name'] === null)
            <p class="fi-farmaadmin-branch-monthly-stats__empty">
                Tu usuario no tiene sucursal asignada. Pide a un administrador que la configure.
            </p>
        @else
            <article class="fi-farmaadmin-today-summary__kpi fi-farmaadmin-today-summary__kpi--primary">
                <div class="fi-farmaadmin-cashier-goal__goal-head">
                    <span>
                        <span class="fi-farmaadmin-today-summary__kpi-label">Avance de la sucursal</span>
                        <span class="fi-farmaadmin-today-summary__kpi-value">{{ Number::currency($progress['branch_total_usd'], 'USD', 'en', 2) }}</span>
                    </span>
                    <span class="fi-farmaadmin-cashier-goal__goal-target">
                        @if ($hasGoal)
                            <span class="fi-farmaadmin-today-summary__kpi-label">Meta del mes</span>
                            <span class="fi-farmaadmin-cashier-goal__goal-amount">{{ Number::currency($goalUsd, 'USD', 'en', 0) }}</span>
                        @else
                            <span class="fi-farmaadmin-today-summary__kpi-label">Sin meta del mes</span>
                        @endif
                    </span>
                </div>

                <div
                    class="fi-farmaadmin-cashier-goal__track"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ number_format($branchBarWidth, 1, '.', '') }}"
                    aria-label="Avance de la sucursal respecto a la meta del mes"
                >
                    <span class="fi-farmaadmin-cashier-goal__fill fi-farmaadmin-cashier-goal__fill--branch" style="width: {{ number_format($branchBarWidth, 2, '.', '') }}%"></span>
                    <span class="fi-farmaadmin-cashier-goal__fill fi-farmaadmin-cashier-goal__fill--cashier" style="width: {{ number_format($cashierBarWidth, 2, '.', '') }}%"></span>
                </div>

                <div class="fi-farmaadmin-cashier-goal__legend">
                    <span><i class="fi-farmaadmin-cashier-goal__swatch fi-farmaadmin-cashier-goal__swatch--cashier"></i> Tu aporte</span>
                    <span><i class="fi-farmaadmin-cashier-goal__swatch fi-farmaadmin-cashier-goal__swatch--branch"></i> Resto de la sucursal</span>
                    @if ($hasGoal && $branchPercent !== null)
                        <strong>{{ number_format($branchPercent, 1, ',', '.') }}% de la meta</strong>
                    @endif
                </div>
            </article>

            <div class="fi-farmaadmin-today-summary__kpis">
                <article class="fi-farmaadmin-today-summary__kpi">
                    <span class="fi-farmaadmin-today-summary__kpi-label">Tus ventas del mes</span>
                    <span class="fi-farmaadmin-today-summary__kpi-value">{{ Number::currency($progress['cashier_total_usd'], 'USD', 'en', 2) }}</span>
                    <span class="fi-farmaadmin-today-summary__kpi-hint">
                        {{ number_format($progress['cashier_operations_count'], 0, ',', '.') }}
                        {{ $progress['cashier_operations_count'] === 1 ? 'operación' : 'operaciones' }}
                    </span>
                </article>
                <article class="fi-farmaadmin-today-summary__kpi">
                    <span class="fi-farmaadmin-today-summary__kpi-label">Tu aporte a la meta</span>
                    <span class="fi-farmaadmin-today-summary__kpi-value">
                        {{ $cashierGoalPercent !== null ? number_format($cashierGoalPercent, 1, ',', '.').'%' : '—' }}
                    </span>
                    <span class="fi-farmaadmin-today-summary__kpi-hint">
                        @if ($progress['cashier_branch_share_percent'] !== null)
                            {{ number_format($progress['cashier_branch_share_percent'], 1, ',', '.') }}% de lo vendido en la sucursal
                        @else
                            Aún no hay ventas en la sucursal este mes
                        @endif
                    </span>
                </article>
                <article class="fi-farmaadmin-today-summary__kpi">
                    <span class="fi-farmaadmin-today-summary__kpi-label">Hoy</span>
                    <span class="fi-farmaadmin-today-summary__kpi-value">{{ Number::currency($progress['cashier_today_usd'], 'USD', 'en', 2) }}</span>
                    <span class="fi-farmaadmin-today-summary__kpi-hint">
                        {{ number_format($progress['cashier_today_operations_count'], 0, ',', '.') }}
                        {{ $progress['cashier_today_operations_count'] === 1 ? 'operación' : 'operaciones' }}
                    </span>
                </article>
                <article class="fi-farmaadmin-today-summary__kpi">
                    <span class="fi-farmaadmin-today-summary__kpi-label">Falta para la meta</span>
                    <span class="fi-farmaadmin-today-summary__kpi-value">
                        {{ $progress['remaining_to_goal_usd'] !== null ? Number::currency($progress['remaining_to_goal_usd'], 'USD', 'en', 2) : '—' }}
                    </span>
                    <span class="fi-farmaadmin-today-summary__kpi-hint">
                        @if ($progress['remaining_to_goal_usd'] === 0.0)
                            ¡Meta alcanzada!
                        @elseif ($hasGoal)
                            Entre toda la sucursal
                        @else
                            La sucursal no tiene meta este mes
                        @endif
                    </span>
                </article>
            </div>
        @endif
    </section>
</x-filament-widgets::widget>
