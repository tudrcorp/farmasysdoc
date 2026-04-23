<x-filament-panels::page>
    <div class="farmadoc-ios-reports-hub mx-auto max-w-6xl space-y-10 pb-10">
        <div class="farmadoc-ios-reports-hero">
            <p class="farmadoc-ios-reports-hero__eyebrow">Exportaciones</p>
            <h2 class="farmadoc-ios-reports-hero__title">Descargue informes en un solo lugar</h2>
            <p class="farmadoc-ios-reports-hero__text">
                Use el rango <strong>Desde / Hasta</strong> cuando el reporte lo indique. Los listados respetan su perfil
                (administrador ve todo; gerencia ve sucursales asignadas).
            </p>
        </div>

        @foreach ($sections as $section)
            <section class="farmadoc-ios-reports-section" aria-labelledby="sec-{{ $loop->index }}">
                <header class="farmadoc-ios-reports-section__head">
                    <h3 id="sec-{{ $loop->index }}" class="farmadoc-ios-reports-section__title">{{ $section['title'] }}</h3>
                    <p class="farmadoc-ios-reports-section__desc">{{ $section['description'] }}</p>
                </header>

                <div class="farmadoc-ios-reports-grid">
                    @foreach ($section['items'] as $item)
                        <article class="farmadoc-ios-report-card">
                            <div class="farmadoc-ios-report-card__body">
                                <h4 class="farmadoc-ios-report-card__title">{{ $item['title'] }}</h4>
                                <p class="farmadoc-ios-report-card__hint">{{ $item['hint'] }}</p>
                            </div>

                            <form
                                class="farmadoc-ios-report-card__form"
                                method="get"
                                action="{{ route('system-reports.download', ['slug' => $item['slug']]) }}"
                                target="_blank"
                                rel="noopener"
                            >
                                @if (! empty($item['dates']))
                                    <div class="farmadoc-ios-report-card__fields">
                                        <label class="farmadoc-ios-field">
                                            <span class="farmadoc-ios-field__label">Desde</span>
                                            <input
                                                class="farmadoc-ios-field__input"
                                                type="date"
                                                name="desde"
                                                value="{{ $defaultDesde }}"
                                                required
                                            />
                                        </label>
                                        <label class="farmadoc-ios-field">
                                            <span class="farmadoc-ios-field__label">Hasta</span>
                                            <input
                                                class="farmadoc-ios-field__input"
                                                type="date"
                                                name="hasta"
                                                value="{{ $defaultHasta }}"
                                                required
                                            />
                                        </label>
                                    </div>
                                @endif

                                @if (! empty($item['extra_fields']))
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
                                    <button type="submit" class="farmadoc-ios-report-download">
                                        <span class="farmadoc-ios-report-download__icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0 3-3m-3 3-3-3M5 19h14" />
                                            </svg>
                                        </span>
                                        <span>Descargar CSV</span>
                                    </button>
                                </div>
                            </form>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
