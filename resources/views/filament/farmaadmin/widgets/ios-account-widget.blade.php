@php
    use Illuminate\Support\Str;

    $displayName = $user ? filament()->getUserName($user) : '';
    $firstName = $user
        ? Str::of($user->name ?? '')->trim()->explode(' ')->filter()->first()
        : '';
@endphp

<x-filament-widgets::widget
    class="fi-wi-widget fi-farmaadmin-account-widget"
    wire:loading.class="fi-farmaadmin-account-widget--switching"
    wire:target="selectedDashboardBranchKey"
>
    <div class="fi-farmaadmin-account-widget__shell">
        <div class="fi-farmaadmin-account-widget__glow" aria-hidden="true"></div>
        <div class="fi-farmaadmin-account-widget__wash" aria-hidden="true"></div>
        <div class="fi-farmaadmin-account-widget__rim" aria-hidden="true"></div>

        <div class="fi-farmaadmin-account-widget__content">
            <div class="fi-farmaadmin-account-widget__identity">
                @if ($user)
                    <x-filament-panels::avatar.user
                        class="fi-farmaadmin-account-widget__avatar shrink-0"
                        size="sm"
                        :user="$user"
                        loading="lazy"
                    />
                @endif

                <div class="fi-farmaadmin-account-widget__text">
                    <p class="fi-farmaadmin-account-widget__eyebrow">
                        {{ __('Bienvenido a :app', ['app' => config('app.name')]) }}
                    </p>
                    <h2 class="fi-farmaadmin-account-widget__title">
                        @if (filled($firstName))
                            {{ __('Hola, :name', ['name' => $firstName]) }}
                        @else
                            {{ $displayName }}
                        @endif
                    </h2>
                    @if ($user && filled($firstName) && $displayName !== $firstName)
                        <p class="fi-farmaadmin-account-widget__subtitle">
                            {{ $displayName }}
                        </p>
                    @endif
                </div>
            </div>

            @if ($showBranchPicker)
                <div
                    class="fi-farmaadmin-account-widget__branches"
                    @if ($showBranchTransition)
                        x-data="farmaadminBranchTransition({ durationMs: {{ $branchTransitionDurationMs }} })"
                        x-on:change.capture="start($event)"
                    @endif
                >
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="selectedDashboardBranchKey"
                        class="fi-farmaadmin-account-widget__branch-select"
                    >
                        <x-slot name="prefix">
                            <span class="fi-farmaadmin-account-widget__branch-select-label">
                                Sucursal
                            </span>
                        </x-slot>

                        <x-filament::input.select
                            wire:model.live="selectedDashboardBranchKey"
                        >
                            <option value="all">
                                Todas las sucursales
                            </option>

                            @foreach ($branchOptions as $branch)
                                <option value="{{ $branch['id'] }}">
                                    {{ $branch['name'] }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    @if ($showBranchTransition)
                        <template x-teleport="body">
                            <div
                                x-cloak
                                x-show="phase === 'entering'"
                                x-transition:enter="fi-farmaadmin-branch-portal--animating"
                                x-transition:enter-start="fi-farmaadmin-branch-portal--from"
                                x-transition:enter-end="fi-farmaadmin-branch-portal--shown"
                                x-transition:leave="fi-farmaadmin-branch-portal--animating"
                                x-transition:leave-start="fi-farmaadmin-branch-portal--shown"
                                x-transition:leave-end="fi-farmaadmin-branch-portal--to"
                                class="fi-farmaadmin-branch-portal"
                                x-bind:class="{ 'fi-farmaadmin-branch-portal--all': mode === 'all' }"
                                x-bind:style="{ '--fi-farmaadmin-branch-portal-duration': `${durationMs}ms` }"
                                role="status"
                                aria-live="polite"
                            >
                                <div class="fi-farmaadmin-branch-portal__backdrop" aria-hidden="true"></div>

                                <div class="fi-farmaadmin-branch-portal__rings" aria-hidden="true">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>

                                <div class="fi-farmaadmin-branch-portal__card">
                                    <div class="fi-farmaadmin-branch-portal__logo">
                                        <img
                                            src="{{ asset('images/logos/farmadoc-ligth.png') }}"
                                            alt="{{ config('app.name') }}"
                                            class="fi-farmaadmin-branch-portal__logo-img fi-farmaadmin-branch-portal__logo-img--light"
                                            decoding="async"
                                        />
                                        <img
                                            src="{{ asset('images/logos/farmadoc-dark.png') }}"
                                            alt=""
                                            class="fi-farmaadmin-branch-portal__logo-img fi-farmaadmin-branch-portal__logo-img--dark"
                                            decoding="async"
                                        />
                                    </div>

                                    <p
                                        class="fi-farmaadmin-branch-portal__eyebrow"
                                        x-text="mode === 'all' ? 'Volviendo a la vista general' : 'Entrando a'"
                                    ></p>

                                    <p class="fi-farmaadmin-branch-portal__title" x-text="branchName"></p>

                                    <div class="fi-farmaadmin-branch-portal__progress" aria-hidden="true">
                                        <span></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>

@if ($showBranchTransition)
    @script
    <script>
        Alpine.data('farmaadminBranchTransition', (config = {}) => ({
            phase: 'idle',
            mode: 'branch',
            branchName: '',
            durationMs: Number(config.durationMs) || 1000,
            settleQuietMs: 250,
            maxWaitMs: 8000,
            pendingRequests: 0,
            hasSeenRequest: false,
            startedAt: 0,
            timers: [],
            settleTimer: null,
            stopInterceptingRequests: null,

            init() {
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    this.durationMs = Math.min(this.durationMs, 400);
                }

                if (typeof window.Livewire?.interceptRequest === 'function') {
                    this.stopInterceptingRequests = window.Livewire.interceptRequest(
                        ({ onFinish, onCancel, onFailure }) => this.trackRequest({ onFinish, onCancel, onFailure }),
                    );
                }
            },

            destroy() {
                this.stopInterceptingRequests?.();
                this.clearTimers();
                this.resetPage();
            },

            start(event) {
                let select = event.target;

                if (! (select instanceof HTMLSelectElement)) {
                    return;
                }

                this.clearTimers();
                this.resetPage();

                this.mode = select.value === 'all' ? 'all' : 'branch';
                this.branchName = this.mode === 'all'
                    ? 'Todas las sucursales'
                    : (select.selectedOptions[0]?.textContent ?? '').trim();
                this.pendingRequests = 0;
                this.hasSeenRequest = false;
                this.startedAt = performance.now();
                this.phase = 'entering';

                document.body.dataset.farmaadminBranchTransition = 'entering';
                document.body.dataset.farmaadminBranchTransitionMode = this.mode;

                this.later(() => {
                    if (! this.hasSeenRequest) {
                        this.reveal();
                    }
                }, this.durationMs);

                this.later(() => this.reveal(), this.maxWaitMs);
            },

            trackRequest({ onFinish, onCancel, onFailure }) {
                if (this.phase !== 'entering') {
                    return;
                }

                this.pendingRequests++;
                this.hasSeenRequest = true;
                clearTimeout(this.settleTimer);

                let isDone = false;
                let done = () => {
                    if (isDone) {
                        return;
                    }

                    isDone = true;
                    this.pendingRequests = Math.max(0, this.pendingRequests - 1);
                    this.scheduleSettle();
                };

                onFinish(done);
                onCancel(done);
                onFailure(done);
            },

            scheduleSettle() {
                clearTimeout(this.settleTimer);

                if (this.phase !== 'entering' || this.pendingRequests > 0) {
                    return;
                }

                let remainingMs = this.durationMs - (performance.now() - this.startedAt);

                this.settleTimer = setTimeout(() => {
                    if (this.pendingRequests === 0) {
                        this.reveal();
                    }
                }, Math.max(this.settleQuietMs, remainingMs));
            },

            reveal() {
                if (this.phase !== 'entering') {
                    return;
                }

                this.clearTimers();
                this.phase = 'revealing';

                let widgets = this.dashboardWidgets();

                widgets.forEach((widget, index) => {
                    widget.style.setProperty('--fi-farmaadmin-branch-reveal-delay', `${index * 80}ms`);
                });

                document.body.dataset.farmaadminBranchTransition = 'revealing';

                this.later(() => {
                    this.phase = 'idle';
                    this.resetPage();
                }, 700 + widgets.length * 80);
            },

            dashboardWidgets() {
                return Array.from(
                    document.querySelectorAll('.fi-page-main .fi-wi-widget:not(.fi-farmaadmin-account-widget)'),
                );
            },

            resetPage() {
                delete document.body.dataset.farmaadminBranchTransition;
                delete document.body.dataset.farmaadminBranchTransitionMode;

                this.dashboardWidgets().forEach((widget) => {
                    widget.style.removeProperty('--fi-farmaadmin-branch-reveal-delay');
                });
            },

            later(callback, delayMs) {
                this.timers.push(setTimeout(callback, delayMs));
            },

            clearTimers() {
                this.timers.forEach((timer) => clearTimeout(timer));
                this.timers = [];
                clearTimeout(this.settleTimer);
                this.settleTimer = null;
            },
        }));
    </script>
    @endscript
@endif
