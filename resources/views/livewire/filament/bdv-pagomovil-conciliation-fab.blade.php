@php($bdvOutcomeOk = $lastSuccess)

<div
    class="farmadoc-bdv-pm-fab-root"
    x-data="farmadocBdvPmFab()"
    x-effect="document.body.classList.toggle('farmadoc-bdv-pm-sheet-open', open)"
    x-on:bdv-pm-conciliation-success.window="onConciliationSuccess()"
    x-on:bdv-pm-sheet-reset.window="showSuccessOverlay = false"
>
    @unless ($hideFab)
        <button
            type="button"
            class="farmadoc-bdv-pm-fab"
            wire:click="openSheet"
            aria-label="Conciliar Pago Móvil BDV"
            title="Conciliar Pago Móvil BDV"
        >
            <span class="farmadoc-bdv-pm-fab__halo" aria-hidden="true"></span>
            <img
                src="{{ $logoUrl }}"
                alt=""
                class="farmadoc-bdv-pm-fab__logo"
                width="28"
                height="28"
                decoding="async"
            />
            <span class="farmadoc-bdv-pm-fab__label">BDV</span>
        </button>
    @endunless

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-on:keydown.escape.window="closeSheet()"
            class="farmadoc-bdv-pm-sheet-portal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="farmadoc-bdv-pm-sheet-title"
        >
            <div
                class="farmadoc-bdv-pm-sheet__backdrop"
                x-show="open"
                x-transition:enter="farmadoc-bdv-pm-sheet-backdrop-enter"
                x-transition:enter-start="farmadoc-bdv-pm-sheet-backdrop-enter-start"
                x-transition:enter-end="farmadoc-bdv-pm-sheet-backdrop-enter-end"
                x-transition:leave="farmadoc-bdv-pm-sheet-backdrop-leave"
                x-transition:leave-start="farmadoc-bdv-pm-sheet-backdrop-leave-start"
                x-transition:leave-end="farmadoc-bdv-pm-sheet-backdrop-leave-end"
                x-on:click="closeSheet()"
            ></div>

            <div
                class="farmadoc-bdv-pm-sheet"
                x-show="open"
                x-transition:enter="farmadoc-bdv-pm-sheet-enter"
                x-transition:enter-start="farmadoc-bdv-pm-sheet-enter-start"
                x-transition:enter-end="farmadoc-bdv-pm-sheet-enter-end"
                x-transition:leave="farmadoc-bdv-pm-sheet-leave"
                x-transition:leave-start="farmadoc-bdv-pm-sheet-leave-start"
                x-transition:leave-end="farmadoc-bdv-pm-sheet-leave-end"
                x-on:click.stop
                x-on:touchstart.passive="onTouchStart($event)"
                x-on:touchmove.passive="onTouchMove($event)"
                x-on:touchend="onTouchEnd()"
                :style="dragStyle"
            >
                <div class="farmadoc-bdv-pm-sheet__handle-wrap" aria-hidden="true">
                    <span class="farmadoc-bdv-pm-sheet__handle"></span>
                </div>

                <header class="farmadoc-bdv-pm-sheet__header">
                    <button
                        type="button"
                        class="farmadoc-bdv-pm-sheet__close"
                        x-on:click="closeSheet()"
                        aria-label="Cerrar"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div class="farmadoc-bdv-pm-sheet__brand">
                        <img
                            src="{{ $fullLogoUrl }}"
                            alt="Banco de Venezuela"
                            class="farmadoc-bdv-pm-sheet__brand-logo"
                            width="180"
                            height="48"
                            decoding="async"
                        />
                    </div>

                    <h2 id="farmadoc-bdv-pm-sheet-title" class="farmadoc-bdv-pm-sheet__title">
                        Conciliar Pago Móvil
                    </h2>
                    <p class="farmadoc-bdv-pm-sheet__subtitle">
                        Valide el comprobante contra BDV (getMovement/v2). Entorno: <strong>{{ $environmentLabel }}</strong>.
                    </p>
                </header>

                <div class="farmadoc-bdv-pm-sheet__body">
                    @if ($lastResult === null)
                        <form wire:submit.prevent="submitConciliation" class="farmadoc-bdv-pm-sheet__form">
                            @include('filament.partials.bdv-pagomovil-conciliation-form-fields', [
                                'idPrefix' => 'bdv-fab',
                                'compact' => true,
                            ])

                            <div class="farmadoc-bdv-pm-sheet__actions">
                                <button
                                    type="submit"
                                    class="farmadoc-bdv-pm-sheet__btn farmadoc-bdv-pm-sheet__btn--primary"
                                    wire:loading.attr="disabled"
                                    wire:target="submitConciliation"
                                >
                                    <span wire:loading.remove wire:target="submitConciliation">Validar con BDV</span>
                                    <span wire:loading wire:target="submitConciliation">Consultando BDV…</span>
                                </button>
                                <button
                                    type="button"
                                    class="farmadoc-bdv-pm-sheet__btn farmadoc-bdv-pm-sheet__btn--ghost"
                                    wire:click="resetForm"
                                    wire:loading.attr="disabled"
                                    wire:target="resetForm,submitConciliation"
                                >
                                    Limpiar
                                </button>
                            </div>
                        </form>
                    @else
                        @include('filament.partials.bdv-pagomovil-conciliation-result', [
                            'lastResult' => $lastResult,
                            'bdvOutcomeOk' => $bdvOutcomeOk,
                        ])

                        <div class="farmadoc-bdv-pm-sheet__actions">
                            <button
                                type="button"
                                class="farmadoc-bdv-pm-sheet__btn farmadoc-bdv-pm-sheet__btn--primary"
                                wire:click="resetForm"
                            >
                                Nueva conciliación
                            </button>
                            @if ($canViewHistory)
                                <a
                                    href="{{ $conciliationsUrl }}"
                                    class="farmadoc-bdv-pm-sheet__btn farmadoc-bdv-pm-sheet__btn--ghost"
                                >
                                    Ver historial
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                <div
                    class="farmadoc-bdv-pm-success-overlay"
                    :class="{
                        'farmadoc-bdv-pm-success-overlay--visible': showSuccessOverlay,
                        'farmadoc-bdv-pm-success-overlay--exiting': successOverlayExiting,
                    }"
                    aria-hidden="true"
                >
                    <div class="farmadoc-bdv-pm-success-overlay__backdrop"></div>
                    <div class="farmadoc-bdv-pm-success-overlay__card">
                        <div class="farmadoc-bdv-pm-success-check">
                            <svg class="farmadoc-bdv-pm-success-check__svg" viewBox="0 0 72 72" width="72" height="72" aria-hidden="true">
                                <circle class="farmadoc-bdv-pm-success-check__circle" cx="36" cy="36" r="32" fill="none" stroke="currentColor" stroke-width="3" />
                                <path class="farmadoc-bdv-pm-success-check__tick" d="M22 37 L32 47 L50 27" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="farmadoc-bdv-pm-success-overlay__title">Pago conciliado</p>
                        <p class="farmadoc-bdv-pm-success-overlay__sub">Respuesta confirmada por BDV</p>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@script
<script>
    Alpine.data('farmadocBdvPmFab', () => ({
        open: $wire.entangle('sheetOpen'),
        showSuccessOverlay: false,
        successOverlayExiting: false,
        dragY: 0,
        touchStartY: null,
        touchStartTime: null,

        get dragStyle() {
            if (this.dragY <= 0) {
                return '';
            }

            return `transform: translateY(${this.dragY}px); transition: none;`;
        },

        init() {
            this.$watch('open', (value) => {
                if (! value) {
                    this.dragY = 0;

                    return;
                }

                this.$nextTick(() => {
                    const firstInput = document.querySelector('.farmadoc-bdv-pm-sheet__form input:not([type="checkbox"])');
                    firstInput?.focus({ preventScroll: true });
                });
            });
        },

        closeSheet() {
            if (! this.open) {
                return;
            }

            this.dragY = 0;
            $wire.closeSheet();
        },

        onConciliationSuccess() {
            if (! this.open) {
                return;
            }

            this.showSuccessOverlay = true;
            this.successOverlayExiting = false;

            window.setTimeout(() => {
                this.successOverlayExiting = true;
                window.setTimeout(() => {
                    this.showSuccessOverlay = false;
                    this.successOverlayExiting = false;
                }, 320);
            }, 1400);
        },

        onTouchStart(event) {
            const sheetBody = event.currentTarget.querySelector('.farmadoc-bdv-pm-sheet__body');
            if (sheetBody && sheetBody.scrollTop > 8) {
                this.touchStartY = null;

                return;
            }

            this.touchStartY = event.touches[0].clientY;
            this.touchStartTime = Date.now();
        },

        onTouchMove(event) {
            if (this.touchStartY === null) {
                return;
            }

            const delta = event.touches[0].clientY - this.touchStartY;
            this.dragY = Math.max(0, delta);
        },

        onTouchEnd() {
            if (this.touchStartY === null) {
                return;
            }

            const elapsed = Date.now() - (this.touchStartTime ?? 0);
            const shouldClose = this.dragY > 120 || (this.dragY > 48 && elapsed < 280);

            this.dragY = 0;
            this.touchStartY = null;
            this.touchStartTime = null;

            if (shouldClose) {
                this.closeSheet();
            }
        },
    }));
</script>
@endscript
