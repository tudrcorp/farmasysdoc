@php
    $disabled = blank(auth()->user()?->branch_id);
@endphp

<div
    wire:ignore
    class="farmadoc-pos-consult"
    x-data="farmadocPosProductConsult({ disabled: @js($disabled) })"
>
    <button
        type="button"
        class="farmadoc-pos-consult__open farmadoc-ios-action farmadoc-ios-action--primary"
        x-ref="openBtn"
        x-bind:disabled="disabled"
        x-on:click="openModal()"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="farmadoc-pos-consult__open-icon" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
        </svg>
        Consultar Productos
        <kbd class="farmadoc-pos-consult__hotkey">F6</kbd>
    </button>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.120ms
            class="farmadoc-pos-consult-portal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="farmadoc-pos-consult-title"
            x-on:keydown.escape.capture.window="if (open) { $event.preventDefault(); $event.stopPropagation(); closeModal(); }"
        >
            <div class="farmadoc-pos-consult__backdrop" aria-hidden="true"></div>

            <div
                class="farmadoc-pos-consult__panel"
                x-trap.noscroll.noautofocus="open"
                x-on:click.stop
            >
                <header class="farmadoc-pos-consult__header">
                    <div class="farmadoc-pos-consult__heading">
                        <h2 id="farmadoc-pos-consult-title">Consultar productos</h2>
                        <p>Busque por nombre, código de barras o principio activo. F6 abre este buscador · Enter agrega a la venta.</p>
                    </div>
                    <button
                        type="button"
                        class="farmadoc-pos-consult__close"
                        tabindex="0"
                        x-on:click="closeModal()"
                    >
                        Cerrar
                    </button>
                </header>

                <div class="farmadoc-pos-consult__search-wrap">
                    <input
                        type="text"
                        x-ref="search"
                        x-model="q"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        enterkeyhint="go"
                        placeholder="Principio activo, código o nombre…"
                        class="farmadoc-pos-consult__search"
                        x-on:input="onSearchInput()"
                        x-on:paste="onPaste($event)"
                        x-on:keydown.space.stop
                        x-on:keydown.arrow-down.prevent="move(1)"
                        x-on:keydown.arrow-up.prevent="move(-1)"
                        x-on:keydown.enter.prevent.stop="confirmSelection()"
                        x-on:keydown.home.prevent="jump(0)"
                        x-on:keydown.end.prevent="jump(-1)"
                    >
                    <p class="farmadoc-pos-consult__hint">
                        <span x-show="loading" x-cloak>Buscando…</span>
                        <span x-show="! loading" x-cloak>
                            <span x-text="results.length"></span> resultado<span x-show="results.length !== 1">s</span>
                            · flechas para recorrer · Enter para agregar · Esc para cerrar
                        </span>
                    </p>
                </div>

                <div class="farmadoc-pos-consult__table-wrap" x-ref="scroller">
                    <table class="farmadoc-pos-consult__table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Principio activo</th>
                                <th class="farmadoc-pos-consult__num">USD</th>
                                <th class="farmadoc-pos-consult__num">VES</th>
                                <th class="farmadoc-pos-consult__num">Existencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in results" :key="row.id">
                                <tr
                                    :id="'farmadoc-pos-consult-row-' + index"
                                    :class="{
                                        'is-active': index === activeIndex,
                                        'is-out': row.out_of_stock,
                                    }"
                                    x-on:click.stop="confirmProduct(row)"
                                    x-on:mouseenter="activeIndex = index"
                                >
                                    <td class="farmadoc-pos-consult__code" x-text="row.code"></td>
                                    <td>
                                        <span class="farmadoc-pos-consult__name" x-text="row.name"></span>
                                    </td>
                                    <td class="farmadoc-pos-consult__pa" x-text="row.active_ingredient"></td>
                                    <td class="farmadoc-pos-consult__num" x-text="row.price_usd"></td>
                                    <td class="farmadoc-pos-consult__num" x-text="row.price_ves"></td>
                                    <td class="farmadoc-pos-consult__num">
                                        <span
                                            class="farmadoc-pos-consult__qty"
                                            :class="{ 'is-zero': row.out_of_stock }"
                                            x-text="row.quantity"
                                        ></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <p
                        class="farmadoc-pos-consult__empty"
                        x-show="! loading && results.length === 0"
                        x-cloak
                    >
                        No hay productos para esa búsqueda.
                    </p>
                </div>
            </div>
        </div>
    </template>
</div>

@script
<script>
    Alpine.data('farmadocPosProductConsult', (config = {}) => ({
        disabled: Boolean(config.disabled),
        open: false,
        q: '',
        results: [],
        activeIndex: 0,
        loading: false,
        adding: false,
        searchSeq: 0,
        searchTimer: null,
        onF6: null,

        init() {
            this.onF6 = (event) => {
                if (event.key !== 'F6' || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) {
                    return;
                }

                if (this.disabled) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (this.open) {
                    this.$nextTick(() => this.focusSearch());

                    return;
                }

                this.openModal();
            };

            window.addEventListener('keydown', this.onF6, true);
        },

        destroy() {
            if (this.onF6) {
                window.removeEventListener('keydown', this.onF6, true);
            }
        },

        async openModal() {
            if (this.disabled) {
                return;
            }

            this.open = true;
            this.q = '';
            this.activeIndex = 0;
            await this.searchNow();
            this.$nextTick(() => this.focusSearch());
        },

        closeModal() {
            this.open = false;
            this.q = '';
            this.results = [];
            this.activeIndex = 0;
            this.clearSearchTimer();
            this.$nextTick(() => this.$refs.openBtn?.focus({ preventScroll: true }));
        },

        searchEl() {
            return this.$refs.search
                ?? document.querySelector('.farmadoc-pos-consult-portal .farmadoc-pos-consult__search');
        },

        readTerm() {
            return String(this.searchEl()?.value ?? this.q ?? '');
        },

        searchTerm() {
            return this.readTerm().replace(/\s+/g, ' ').trim();
        },

        focusSearch() {
            const input = this.searchEl();
            if (! input) {
                return;
            }

            input.focus({ preventScroll: true });
            input.select?.();
        },

        clearSearchTimer() {
            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
                this.searchTimer = null;
            }
        },

        onSearchInput() {
            this.q = this.readTerm();
            this.scheduleSearch();
        },

        onPaste(event) {
            const pasted = String(event.clipboardData?.getData('text') ?? '').replace(/\s+/g, ' ').trim();
            if (pasted === '') {
                return;
            }

            event.preventDefault();

            const input = this.searchEl();
            if (input) {
                input.value = pasted;
            }
            this.q = pasted;
            this.searchNow();
        },

        scheduleSearch() {
            this.clearSearchTimer();
            this.searchTimer = setTimeout(() => {
                this.searchNow();
            }, 50);
        },

        async searchNow() {
            this.clearSearchTimer();
            this.loading = true;
            const seq = ++this.searchSeq;
            const term = this.searchTerm();

            try {
                const rows = await $wire.call('searchPosConsultProducts', term);
                if (seq !== this.searchSeq) {
                    return [];
                }

                this.results = Array.isArray(rows) ? rows : [];
                this.activeIndex = this.results.length > 0 ? 0 : -1;
                this.$nextTick(() => this.scrollActiveIntoView());

                return this.results;
            } catch (error) {
                if (seq !== this.searchSeq) {
                    return [];
                }

                this.results = [];
                this.activeIndex = -1;

                return [];
            } finally {
                if (seq === this.searchSeq) {
                    this.loading = false;
                }
            }
        },

        move(delta) {
            if (this.results.length === 0) {
                return;
            }

            const next = this.activeIndex + delta;
            this.activeIndex = (next + this.results.length) % this.results.length;
            this.$nextTick(() => this.scrollActiveIntoView());
        },

        jump(target) {
            if (this.results.length === 0) {
                return;
            }

            this.activeIndex = target < 0 ? this.results.length - 1 : 0;
            this.$nextTick(() => this.scrollActiveIntoView());
        },

        scrollActiveIntoView() {
            if (this.activeIndex < 0) {
                return;
            }

            document.getElementById(`farmadoc-pos-consult-row-${this.activeIndex}`)
                ?.scrollIntoView({ block: 'nearest' });
        },

        looksLikeCode(term) {
            const compact = String(term ?? '').replace(/[\s\-]/g, '');

            return /^\d{8,}$/.test(compact);
        },

        pickRowForTerm(term) {
            if (this.results.length === 0) {
                return null;
            }

            const compact = term.replace(/[\s\-]/g, '');
            const exact = this.results.find((row) => {
                const code = String(row.code ?? '').trim();
                const codeCompact = code.replace(/[\s\-]/g, '');

                return code === term
                    || codeCompact === compact
                    || (compact.length >= 8 && /^\d+$/.test(compact) && codeCompact.endsWith(compact));
            });

            if (exact) {
                return exact;
            }

            if (this.looksLikeCode(term)) {
                return this.results.length === 1 ? this.results[0] : null;
            }

            return this.results[this.activeIndex] ?? this.results[0];
        },

        async confirmProduct(row) {
            await this.addSelectedRow(row);
        },

        async confirmSelection() {
            if (this.adding) {
                return;
            }

            const term = this.searchTerm();
            const pendingSearch = this.searchTimer !== null || this.loading;
            const selectedRow = pendingSearch || this.looksLikeCode(term)
                ? null
                : (this.results[this.activeIndex] ?? null);

            if (selectedRow?.id) {
                await this.addSelectedRow(selectedRow);

                return;
            }

            await this.searchNow();

            const row = this.looksLikeCode(term)
                ? this.pickRowForTerm(term)
                : (this.results[this.activeIndex] ?? this.results[0] ?? null);

            await this.addSelectedRow(row);
        },

        async addSelectedRow(row) {
            if (this.adding) {
                return;
            }

            const productId = Number(row?.id ?? 0);
            if (productId <= 0) {
                this.$nextTick(() => this.focusSearch());

                return;
            }

            this.adding = true;

            try {
                await $wire.call('addPosConsultProduct', productId);
            } finally {
                this.adding = false;
                this.q = '';
                const input = this.searchEl();
                if (input) {
                    input.value = '';
                }
                this.activeIndex = 0;
                await this.searchNow();
                this.$nextTick(() => this.focusSearch());
            }
        },
    }));
</script>
@endscript
