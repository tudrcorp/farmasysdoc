<x-filament-panels::page>
    <div
        class="farmadoc-ios-reports-hub"
        x-data="farmadocSystemReportsHub()"
        x-cloak
    >
        {{-- Hero --}}
        <header class="farmadoc-ios-reports-hero">
            <div class="farmadoc-ios-reports-hero__grid">
                <div class="farmadoc-ios-reports-hero__copy">
                    <p class="farmadoc-ios-reports-hero__eyebrow">Centro de exportaciones</p>
                    <h2 class="farmadoc-ios-reports-hero__title">Encuentre y descargue el informe que necesita</h2>
                    <p class="farmadoc-ios-reports-hero__text">
                        Archivos <strong>CSV</strong> en UTF-8 con separador <strong>punto y coma</strong>.
                        Los datos respetan el alcance de sucursal de su perfil.
                    </p>
                </div>
                <div class="farmadoc-ios-reports-hero__stats" aria-label="Resumen del catálogo">
                    <div class="farmadoc-ios-reports-stat">
                        <span class="farmadoc-ios-reports-stat__value">{{ $reportCount }}</span>
                        <span class="farmadoc-ios-reports-stat__label">Reportes</span>
                    </div>
                    <div class="farmadoc-ios-reports-stat">
                        <span class="farmadoc-ios-reports-stat__value">{{ $sectionCount }}</span>
                        <span class="farmadoc-ios-reports-stat__label">Categorías</span>
                    </div>
                    <div class="farmadoc-ios-reports-stat farmadoc-ios-reports-stat--format">
                        <span class="farmadoc-ios-reports-stat__badge">CSV</span>
                        <span class="farmadoc-ios-reports-stat__label">Formato</span>
                    </div>
                </div>
            </div>
        </header>

        {{-- Toolbar --}}
        <div class="farmadoc-ios-reports-toolbar">
            <div class="farmadoc-ios-reports-search">
                <svg class="farmadoc-ios-reports-search__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z" />
                </svg>
                <input
                    type="search"
                    class="farmadoc-ios-reports-search__input"
                    placeholder="Buscar por nombre o descripción…"
                    x-model.debounce.200ms="query"
                    aria-label="Buscar reportes"
                />
                <button
                    type="button"
                    class="farmadoc-ios-reports-search__clear"
                    x-show="query.length > 0"
                    x-on:click="query = ''"
                    aria-label="Limpiar búsqueda"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="farmadoc-ios-reports-filters" role="group" aria-label="Filtrar reportes">
                <button type="button" class="farmadoc-ios-reports-filter" :class="{ 'is-active': filter === 'all' }" x-on:click="filter = 'all'">Todos</button>
                <button type="button" class="farmadoc-ios-reports-filter" :class="{ 'is-active': filter === 'dated' }" x-on:click="filter = 'dated'">Con fechas</button>
                <button type="button" class="farmadoc-ios-reports-filter" :class="{ 'is-active': filter === 'instant' }" x-on:click="filter = 'instant'">Al instante</button>
                <button type="button" class="farmadoc-ios-reports-filter" :class="{ 'is-active': filter === 'options' }" x-on:click="filter = 'options'">Con opciones</button>
            </div>
        </div>

        <div class="farmadoc-ios-reports-layout">
            <nav class="farmadoc-ios-reports-nav" aria-label="Categorías de reportes">
                <p class="farmadoc-ios-reports-nav__label">Ir a categoría</p>
                <ul class="farmadoc-ios-reports-nav__list">
                    @foreach ($sections as $section)
                        <li x-show="sectionHasVisible('{{ $section['key'] }}')" x-cloak>
                            <button
                                type="button"
                                class="farmadoc-ios-reports-nav__link farmadoc-ios-reports-nav__link--{{ $section['accent'] }}"
                                x-on:click="scrollToSection('{{ $section['key'] }}')"
                            >
                                {{ $section['title'] }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="farmadoc-ios-reports-main">
                <div class="farmadoc-ios-reports-pills" aria-label="Categorías">
                    @foreach ($sections as $section)
                        <button
                            type="button"
                            class="farmadoc-ios-reports-pill farmadoc-ios-reports-pill--{{ $section['accent'] }}"
                            x-show="sectionHasVisible('{{ $section['key'] }}')"
                            x-cloak
                            x-on:click="scrollToSection('{{ $section['key'] }}')"
                        >
                            {{ $section['title'] }}
                        </button>
                    @endforeach
                </div>

                <div class="farmadoc-ios-reports-empty" x-show="! hasAnyVisible()" x-cloak>
                    <div class="farmadoc-ios-reports-empty__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M8.5 11a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM3 7.5V6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v1.5" />
                        </svg>
                    </div>
                    <h3 class="farmadoc-ios-reports-empty__title">No hay reportes que coincidan</h3>
                    <p class="farmadoc-ios-reports-empty__text">Pruebe otro término de búsqueda o quite los filtros activos.</p>
                    <button type="button" class="farmadoc-ios-reports-empty__btn" x-on:click="resetFilters()">
                        Ver todos los reportes
                    </button>
                </div>

                @foreach ($sections as $section)
                    <section
                        id="reports-sec-{{ $section['key'] }}"
                        class="farmadoc-ios-reports-section farmadoc-ios-reports-section--{{ $section['accent'] }}"
                        data-section-key="{{ $section['key'] }}"
                        x-show="sectionHasVisible('{{ $section['key'] }}')"
                        x-cloak
                    >
                        <header class="farmadoc-ios-reports-section__head">
                            <div class="farmadoc-ios-reports-section__icon farmadoc-ios-reports-section__icon--{{ $section['accent'] }}" aria-hidden="true">
                                @include('filament.pages.reports.partials.section-icon', ['icon' => $section['icon']])
                            </div>
                            <div class="farmadoc-ios-reports-section__meta">
                                <h3 class="farmadoc-ios-reports-section__title">{{ $section['title'] }}</h3>
                                <p class="farmadoc-ios-reports-section__desc">{{ $section['description'] }}</p>
                            </div>
                            <span class="farmadoc-ios-reports-section__count">{{ count($section['items']) }} reportes</span>
                        </header>

                        <div class="farmadoc-ios-reports-grid">
                            @foreach ($section['items'] as $item)
                                @php
                                    $searchText = mb_strtolower($item['title'].' '.$item['hint'].' '.$item['slug']);
                                    $hasFilters = ! empty($item['extra_fields']);
                                    $hasDates = (bool) ($item['dates'] ?? false);
                                @endphp
                                <article
                                    class="farmadoc-ios-report-card farmadoc-ios-report-card--{{ $section['accent'] }}"
                                    data-search="{{ $searchText }}"
                                    data-dates="{{ $hasDates ? '1' : '0' }}"
                                    data-filters="{{ $hasFilters ? '1' : '0' }}"
                                    data-section="{{ $section['key'] }}"
                                    x-show="cardVisible($el)"
                                    x-cloak
                                >
                                    <div class="farmadoc-ios-report-card__top">
                                        <div class="farmadoc-ios-report-card__body">
                                            <h4 class="farmadoc-ios-report-card__title">{{ $item['title'] }}</h4>
                                            <p class="farmadoc-ios-report-card__hint">{{ $item['hint'] }}</p>
                                        </div>
                                        <div class="farmadoc-ios-report-card__badges">
                                            <span class="farmadoc-ios-report-badge farmadoc-ios-report-badge--csv">CSV</span>
                                            @if ($hasDates)
                                                <span class="farmadoc-ios-report-badge farmadoc-ios-report-badge--dates">Fechas</span>
                                            @elseif (! $hasFilters)
                                                <span class="farmadoc-ios-report-badge farmadoc-ios-report-badge--instant">Instantáneo</span>
                                            @endif
                                            @if ($hasFilters)
                                                <span class="farmadoc-ios-report-badge farmadoc-ios-report-badge--options">Opciones</span>
                                            @endif
                                        </div>
                                    </div>

                                    <form
                                        class="farmadoc-ios-report-card__form"
                                        method="get"
                                        action="{{ route('system-reports.download', ['slug' => $item['slug']]) }}"
                                        target="_blank"
                                        rel="noopener"
                                        x-on:submit="markDownloading('{{ $item['slug'] }}')"
                                    >
                                        @if ($hasDates)
                                            <div class="farmadoc-ios-report-card__date-block">
                                                <div class="farmadoc-ios-report-card__presets" role="group" aria-label="Atajos de rango">
                                                    @foreach ($datePresets as $preset)
                                                        <button
                                                            type="button"
                                                            class="farmadoc-ios-report-preset"
                                                            data-desde="{{ $preset['desde'] }}"
                                                            data-hasta="{{ $preset['hasta'] }}"
                                                            x-on:click="applyPreset($event)"
                                                        >
                                                            {{ $preset['label'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                                <div class="farmadoc-ios-report-card__fields">
                                                    <label class="farmadoc-ios-field">
                                                        <span class="farmadoc-ios-field__label">Desde</span>
                                                        <input class="farmadoc-ios-field__input" type="date" name="desde" value="{{ $defaultDesde }}" required />
                                                    </label>
                                                    <label class="farmadoc-ios-field">
                                                        <span class="farmadoc-ios-field__label">Hasta</span>
                                                        <input class="farmadoc-ios-field__input" type="date" name="hasta" value="{{ $defaultHasta }}" required />
                                                    </label>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($hasFilters)
                                            <div class="farmadoc-ios-report-card__fields farmadoc-ios-report-card__fields--stack">
                                                @foreach ($item['extra_fields'] as $field)
                                                    <label class="farmadoc-ios-field farmadoc-ios-field--grow">
                                                        <span class="farmadoc-ios-field__label">{{ $field['label'] }}</span>
                                                        @if (($field['type'] ?? '') === 'select')
                                                            <select class="farmadoc-ios-field__select" name="{{ $field['name'] }}">
                                                                @foreach ($field['options'] ?? [] as $val => $lab)
                                                                    <option value="{{ $val }}">{{ $lab }}</option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="farmadoc-ios-report-card__actions">
                                            <button
                                                type="submit"
                                                class="farmadoc-ios-report-download"
                                                :class="{ 'is-loading': downloading === '{{ $item['slug'] }}' }"
                                                :disabled="downloading === '{{ $item['slug'] }}'"
                                            >
                                                <span class="farmadoc-ios-report-download__icon" aria-hidden="true">
                                                    <svg x-show="downloading !== '{{ $item['slug'] }}'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0 3-3m-3 3-3-3M5 19h14" />
                                                    </svg>
                                                    <svg x-show="downloading === '{{ $item['slug'] }}'" class="farmadoc-ios-report-download__spinner size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                                    </svg>
                                                </span>
                                                <span x-text="downloading === '{{ $item['slug'] }}' ? 'Generando…' : 'Descargar CSV'"></span>
                                            </button>
                                        </div>
                                    </form>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('farmadocSystemReportsHub', () => ({
            query: '',
            filter: 'all',
            downloading: null,

            get normalizedQuery() {
                return this.query.trim().toLowerCase();
            },

            cardVisible(el) {
                void this.query;
                void this.filter;

                const search = el.dataset.search ?? '';
                const hasDates = el.dataset.dates === '1';
                const hasFilters = el.dataset.filters === '1';

                if (this.normalizedQuery !== '' && ! search.includes(this.normalizedQuery)) {
                    return false;
                }

                if (this.filter === 'dated' && ! hasDates) {
                    return false;
                }

                if (this.filter === 'instant' && (hasDates || hasFilters)) {
                    return false;
                }

                if (this.filter === 'options' && ! hasFilters) {
                    return false;
                }

                return true;
            },

            sectionHasVisible(sectionKey) {
                void this.query;
                void this.filter;

                return Array.from(document.querySelectorAll(`[data-section="${sectionKey}"]`))
                    .some((el) => this.cardVisible(el));
            },

            hasAnyVisible() {
                void this.query;
                void this.filter;

                return Array.from(document.querySelectorAll('[data-section]'))
                    .some((el) => this.cardVisible(el));
            },

            applyPreset(event) {
                const button = event.currentTarget;
                const form = button.closest('form');
                if (! form) {
                    return;
                }

                const desde = form.querySelector('[name="desde"]');
                const hasta = form.querySelector('[name="hasta"]');

                if (desde) {
                    desde.value = button.dataset.desde ?? '';
                }
                if (hasta) {
                    hasta.value = button.dataset.hasta ?? '';
                }

                form.querySelectorAll('.farmadoc-ios-report-preset').forEach((preset) => {
                    preset.classList.remove('is-active');
                });
                button.classList.add('is-active');
            },

            scrollToSection(key) {
                const el = document.getElementById('reports-sec-' + key);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            },

            resetFilters() {
                this.query = '';
                this.filter = 'all';
            },

            markDownloading(slug) {
                this.downloading = slug;
                window.setTimeout(() => {
                    if (this.downloading === slug) {
                        this.downloading = null;
                    }
                }, 4000);
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
