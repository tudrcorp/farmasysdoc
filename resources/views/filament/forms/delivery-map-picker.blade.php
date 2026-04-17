@php
    $fieldWrapperView = $getFieldWrapperView();
    $extraAttributeBag = $getExtraAttributeBag();
    $lat = $get('delivery_latitude');
    $lng = $get('delivery_longitude');
    $uid = 'dm_'.preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $getStatePath());
    $hasCoords = is_numeric($lat) && is_numeric($lng);
    $googleBrowserKey = (string) config('services.google.maps_browser_api_key', '');
    $useGoogleMaps = $googleBrowserKey !== '';
    $googleMapIdLight = trim((string) config('services.google.maps_map_id_light', ''));
    $googleMapIdDark = trim((string) config('services.google.maps_map_id_dark', ''));
    /** Prefijo Livewire del formulario (p. ej. `data` en Create/Edit record), para $set desde JS. */
    $livewireFormStatePrefix = filled($field->getContainer()->getStatePath())
        ? (string) $field->getContainer()->getStatePath()
        : 'data';
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="fi-fo-delivery-map-picker"
>
    <div {{ \Filament\Support\prepare_inherited_attributes($extraAttributeBag)->class(['space-y-2']) }}>
        {{-- Mapa estilo localizador: etiqueta, campo con pin, estado; wire:ignore evita que Livewire destruya el mapa. --}}
        <div wire:ignore class="fi-fo-delivery-map-picker__stack w-full">
            <div
                data-delivery-map-shell="1"
                class="relative h-80 min-h-[20rem] w-full overflow-hidden rounded-xl border border-gray-200 bg-zinc-100 shadow-sm dark:border-white/10 dark:bg-zinc-900/40"
            >
                @unless ($useGoogleMaps)
                    <div
                        class="absolute inset-0 z-[5] flex flex-col items-center justify-center gap-3 bg-zinc-100/95 p-6 text-center dark:bg-zinc-900/95"
                    >
                        <p class="max-w-md text-sm font-medium text-gray-800 dark:text-gray-100">
                            Este mapa usa <strong>Google Maps</strong> en el panel. No está activo porque falta la clave
                            del navegador.
                        </p>
                        <p class="max-w-md text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                            En el archivo <code class="rounded bg-gray-200/80 px-1 py-0.5 text-[0.7rem] dark:bg-white/10">.env</code>
                            define
                            <code class="rounded bg-gray-200/80 px-1 py-0.5 text-[0.7rem] dark:bg-white/10">GOOGLE_MAPS_BROWSER_API_KEY</code>
                            (clave distinta a la de servidor; restricción por referrer HTTP de tu dominio). En Google Cloud
                            habilita <strong>Maps JavaScript API</strong>, <strong>Places API</strong> y
                            <strong>Geocoding API</strong>. Luego ejecuta
                            <code class="rounded bg-gray-200/80 px-1 py-0.5 text-[0.7rem] dark:bg-white/10">php artisan config:clear</code>.
                        </p>
                    </div>
                @endunless
                @if ($useGoogleMaps)
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 z-[1000] max-h-[55%] overflow-y-auto p-2 sm:max-h-none sm:overflow-visible"
                >
                    <div class="pointer-events-auto space-y-2">
                        <div class="mx-auto w-full max-w-[28rem] px-0.5">
                            <div
                                class="fi-delivery-map-search-pill flex h-[3.25rem] items-stretch overflow-hidden rounded-full bg-white shadow-[0_2px_14px_rgba(0,0,0,0.18)] ring-1 ring-black/[0.06] dark:bg-gray-950 dark:ring-white/12"
                            >
                                <label class="sr-only" for="{{ $uid }}_search">Buscar en Google Maps</label>
                                <input
                                    id="{{ $uid }}_search"
                                    type="text"
                                    data-delivery-map-search="1"
                                    autocomplete="off"
                                    placeholder="Buscar en Google Maps"
                                    class="min-w-0 flex-1 border-0 bg-transparent py-2 pl-4 pr-1 text-[0.9375rem] text-gray-900 placeholder:text-gray-500 focus:outline-none focus:ring-0 dark:text-gray-100 dark:placeholder:text-gray-400"
                                />
                                <button
                                    type="button"
                                    data-delivery-map-search-submit="1"
                                    class="flex shrink-0 items-center justify-center px-3 text-gray-500 transition hover:bg-gray-50 hover:text-gray-800 dark:hover:bg-white/5 dark:hover:text-gray-200"
                                    title="Buscar"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.75"
                                        stroke="currentColor"
                                        class="size-5"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                                        />
                                    </svg>
                                </button>
                                <div class="flex shrink-0 items-center pr-2 pl-1">
                                    <button
                                        type="button"
                                        data-delivery-map-geolocate="1"
                                        class="group flex size-10 items-center justify-center bg-transparent p-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0d9488] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950"
                                        title="Mi ubicación"
                                    >
                                        <span
                                            class="flex size-9 rotate-45 items-center justify-center rounded-md bg-[#0d9488] text-white shadow-md ring-1 ring-black/10 transition group-hover:bg-[#0f766e] dark:ring-white/15"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.75"
                                                stroke="currentColor"
                                                class="size-[1.1rem] -rotate-45 text-white"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M19.5 10.5c0 7.125-7.5 11.25-7.5 11.25S4.5 17.625 4.5 10.5a7.5 7.5 0 1 1 15 0Z"
                                                />
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                            <div class="px-0.5">
                                <span
                                    class="block text-[0.65rem] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400"
                                >
                                    Explorar cerca del mapa
                                </span>
                                <span
                                    class="mt-0.5 block text-[0.7rem] leading-snug text-gray-500 dark:text-gray-400"
                                >
                                    También puedes buscar arriba, mover el mapa y usar una categoría. Pulsa un pin
                                    turquesa para fijar la entrega; arrastra el pin rojo si hace falta. Clic en el mapa
                                    también marca el punto.
                                </span>
                            </div>
                            <div
                                data-delivery-map-poi-toolbar="1"
                                class="flex max-w-full flex-wrap gap-1.5 rounded-xl border border-gray-200/90 bg-white/95 p-2 shadow-md ring-1 ring-gray-950/5 backdrop-blur-sm dark:border-white/15 dark:bg-gray-950/95 dark:ring-white/10"
                            >
                                <button
                                    type="button"
                                    data-place-type="restaurant"
                                    class="fi-delivery-map-poi-chip inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[0.7rem] font-medium text-gray-800 shadow-sm hover:border-primary-400 hover:bg-primary-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-100 dark:hover:border-primary-500 dark:hover:bg-primary-950/40"
                                >
                                    <span class="text-base leading-none" aria-hidden="true">🍴</span>
                                    Restaurantes
                                </button>
                                <button
                                    type="button"
                                    data-place-type="lodging"
                                    class="fi-delivery-map-poi-chip inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[0.7rem] font-medium text-gray-800 shadow-sm hover:border-primary-400 hover:bg-primary-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-100 dark:hover:border-primary-500 dark:hover:bg-primary-950/40"
                                >
                                    <span class="text-base leading-none" aria-hidden="true">🛏</span>
                                    Hoteles
                                </button>
                                <button
                                    type="button"
                                    data-place-type="tourist_attraction"
                                    class="fi-delivery-map-poi-chip inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[0.7rem] font-medium text-gray-800 shadow-sm hover:border-primary-400 hover:bg-primary-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-100 dark:hover:border-primary-500 dark:hover:bg-primary-950/40"
                                >
                                    <span class="text-base leading-none" aria-hidden="true">📷</span>
                                    Qué hacer
                                </button>
                                <button
                                    type="button"
                                    data-place-type="museum"
                                    class="fi-delivery-map-poi-chip inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[0.7rem] font-medium text-gray-800 shadow-sm hover:border-primary-400 hover:bg-primary-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-100 dark:hover:border-primary-500 dark:hover:bg-primary-950/40"
                                >
                                    <span class="text-base leading-none" aria-hidden="true">🏛</span>
                                    Museos
                                </button>
                                <button
                                    type="button"
                                    data-place-type="transit_station"
                                    class="fi-delivery-map-poi-chip inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[0.7rem] font-medium text-gray-800 shadow-sm hover:border-primary-400 hover:bg-primary-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-100 dark:hover:border-primary-500 dark:hover:bg-primary-950/40"
                                >
                                    <span class="text-base leading-none" aria-hidden="true">🚇</span>
                                    Transporte
                                </button>
                                <button
                                    type="button"
                                    data-place-type="pharmacy"
                                    class="fi-delivery-map-poi-chip inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[0.7rem] font-medium text-gray-800 shadow-sm hover:border-primary-400 hover:bg-primary-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-100 dark:hover:border-primary-500 dark:hover:bg-primary-950/40"
                                >
                                    <span class="text-base leading-none" aria-hidden="true">➕</span>
                                    Farmacias
                                </button>
                                <button
                                    type="button"
                                    data-place-type="atm"
                                    class="fi-delivery-map-poi-chip inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[0.7rem] font-medium text-gray-800 shadow-sm hover:border-primary-400 hover:bg-primary-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-100 dark:hover:border-primary-500 dark:hover:bg-primary-950/40"
                                >
                                    <span class="text-base leading-none" aria-hidden="true">🏧</span>
                                    Cajeros
                                </button>
                                <button
                                    type="button"
                                    data-delivery-map-clear-pois="1"
                                    class="inline-flex items-center rounded-full border border-dashed border-gray-300 px-2.5 py-1 text-[0.7rem] font-medium text-gray-600 hover:border-gray-400 dark:border-white/25 dark:text-gray-300"
                                >
                                    Quitar pins
                                </button>
                            </div>
                        <div
                            data-delivery-map-status="1"
                            role="status"
                            class="hidden rounded-lg border px-2.5 py-2 text-xs font-medium leading-snug"
                        ></div>
                    </div>
                </div>
                <div
                    id="{{ $uid }}"
                    data-delivery-map-canvas="1"
                    data-map-provider="google"
                    data-nominatim-reverse-url="{{ route('geo.nominatim.reverse') }}"
                    data-livewire-state-prefix="{{ e($livewireFormStatePrefix) }}"
                    data-google-maps-key="{{ e($googleBrowserKey) }}"
                    @if ($googleMapIdLight !== '')
                        data-google-map-id-light="{{ e($googleMapIdLight) }}"
                    @endif
                    @if ($googleMapIdDark !== '')
                        data-google-map-id-dark="{{ e($googleMapIdDark) }}"
                    @endif
                    data-initial-lat="{{ $hasCoords ? e((string) $lat) : '' }}"
                    data-initial-lng="{{ $hasCoords ? e((string) $lng) : '' }}"
                    class="fi-fo-delivery-map-picker__canvas absolute inset-0 size-full"
                ></div>
                @endif
            </div>
        </div>
        @if ($useGoogleMaps)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Mapa <strong>Google Maps</strong>. Geocodificación sesgada a Venezuela; si Google no responde en cliente,
                se usa respaldo en servidor. Opcional:
                <code class="rounded bg-gray-100 px-1 py-0.5 text-[0.7rem] dark:bg-white/10">GOOGLE_MAPS_MAP_ID_LIGHT</code>
                /
                <code class="rounded bg-gray-100 px-1 py-0.5 text-[0.7rem] dark:bg-white/10">GOOGLE_MAPS_MAP_ID_DARK</code>
                para estilo vectorial en Cloud Console.
            </p>
        @endif
    </div>
</x-dynamic-component>

@once
    <style>
        .pac-container {
            z-index: 100000 !important;
        }
    </style>
    <script>
        (function () {
            /** Centro y área de sesgo por defecto: Caracas, Venezuela */
            const CARACAS_DEFAULT = { lat: 10.4806, lng: -66.9036 };

            function getDeliveryMapShell(mapCanvasEl) {
                return (
                    (mapCanvasEl && mapCanvasEl.closest && mapCanvasEl.closest('[data-delivery-map-shell]')) ||
                    (mapCanvasEl && mapCanvasEl.parentElement) ||
                    null
                );
            }

            function setDeliveryMapStatus(mapCanvasEl, message, variant) {
                const shell = getDeliveryMapShell(mapCanvasEl);
                const box = shell && shell.querySelector('[data-delivery-map-status]');
                if (!box) {
                    return;
                }
                const base = 'rounded-lg border px-2.5 py-2 text-xs font-medium leading-snug';
                const themes = {
                    error:
                        'border-red-200 bg-red-50 text-red-800 dark:border-red-500/40 dark:bg-red-950/50 dark:text-red-200',
                    success:
                        'border-primary-200 bg-primary-50 text-primary-900 dark:border-primary-500/40 dark:bg-primary-950/40 dark:text-primary-100',
                    info: 'border-gray-200 bg-white text-gray-700 dark:border-white/15 dark:bg-gray-900 dark:text-gray-200',
                };
                const v = variant === 'error' || variant === 'success' ? variant : 'info';
                if (!message) {
                    box.textContent = '';
                    box.className = base + ' hidden';
                    box.setAttribute('data-delivery-map-status', '1');
                    return;
                }
                box.textContent = message;
                box.className = base + ' ' + (themes[v] || themes.info);
                box.setAttribute('data-delivery-map-status', '1');
            }

            function deliveryStateKey(prefix, name) {
                const p = String(prefix || 'data').replace(/\.+$/, '');
                return p === '' ? name : p + '.' + name;
            }

            /**
             * Elige el $wire que realmente expone el estado del formulario (p. ej. `data` en Filament).
             * No usar solo el componente “más externo”: el layout/panel puede ser Livewire y no tener `data`.
             */
            function pickWireForStatePrefix(wires, statePrefix) {
                const prefix = String(statePrefix || 'data').replace(/\.+$/, '') || 'data';
                for (let i = 0; i < wires.length; i++) {
                    const w = wires[i];
                    if (!w || typeof w.$get !== 'function' || typeof w.$set !== 'function') {
                        continue;
                    }
                    try {
                        if (w.$get(prefix) !== undefined) {
                            return w;
                        }
                    } catch (e) {
                        /* ruta inválida en este componente */
                    }
                }
                return wires.length ? wires[0] : null;
            }

            /**
             * Recorre ancestros y acumula instancias $wire (de abajo arriba: primero el más interno).
             */
            function collectWireStackFromDom(el) {
                const stack = [];
                let node = el;
                while (node) {
                    const lw = node.__livewire;
                    if (lw && lw.$wire && typeof lw.$wire.$set === 'function') {
                        stack.push(lw.$wire);
                    }
                    node = node.parentElement;
                }
                return stack;
            }

            /**
             * Resuelve el $wire del formulario leyendo `data-livewire-state-prefix` del canvas del mapa.
             */
            function getWireForElement(el) {
                if (!el) {
                    return null;
                }
                const statePrefix =
                    el.getAttribute && el.getAttribute('data-livewire-state-prefix')
                        ? el.getAttribute('data-livewire-state-prefix')
                        : 'data';

                const fromDom = collectWireStackFromDom(el);
                const pickedDom = pickWireForStatePrefix(fromDom, statePrefix);
                if (pickedDom) {
                    return pickedDom;
                }

                const byClosest = el.closest?.('[wire\\:id]');
                if (byClosest && window.Livewire && typeof window.Livewire.find === 'function') {
                    const cid = byClosest.getAttribute('wire:id');
                    if (cid) {
                        const w = window.Livewire.find(cid);
                        if (w && typeof w.$set === 'function') {
                            const picked = pickWireForStatePrefix([w], statePrefix);
                            if (picked) {
                                return picked;
                            }
                            return w;
                        }
                    }
                }
                if (window.Livewire && typeof window.Livewire.all === 'function') {
                    try {
                        const comps = window.Livewire.all();
                        const candidates = [];
                        for (let i = 0; i < comps.length; i++) {
                            const comp = comps[i];
                            const root = comp.el;
                            if (!root || !root.contains || !root.contains(el)) {
                                continue;
                            }
                            if (!comp.$wire || typeof comp.$wire.$set !== 'function') {
                                continue;
                            }
                            let d = 0;
                            let n = el;
                            while (n && n !== root) {
                                d++;
                                n = n.parentElement;
                            }
                            if (n === root) {
                                candidates.push({ depth: d, wire: comp.$wire });
                            }
                        }
                        candidates.sort(function (a, b) {
                            return a.depth - b.depth;
                        });
                        const ordered = candidates.map(function (c) {
                            return c.wire;
                        });
                        const pickedAll = pickWireForStatePrefix(ordered, statePrefix);
                        if (pickedAll) {
                            return pickedAll;
                        }
                        if (ordered.length) {
                            return ordered[0];
                        }
                    } catch (e) {
                        /* noop */
                    }
                }
                let walk = el;
                while (walk) {
                    const id = walk.getAttribute?.('wire:id');
                    if (id && window.Livewire && typeof window.Livewire.find === 'function') {
                        const w = window.Livewire.find(id);
                        if (w && typeof w.$set === 'function') {
                            return w;
                        }
                    }
                    walk = walk.parentElement;
                }
                return null;
            }

            function syncCoordsToForm(wire, statePrefix, lat, lng) {
                if (!wire || typeof wire.$set !== 'function') {
                    return;
                }
                void wire.$set(deliveryStateKey(statePrefix, 'delivery_latitude'), lat);
                void wire.$set(deliveryStateKey(statePrefix, 'delivery_longitude'), lng);
            }

            /**
             * Rellena dirección, ciudad y estado desde componentes tipo Google Geocoder.
             */
            function applyGoogleAddressComponents(wire, statePrefix, formattedAddress, components, lat, lng) {
                if (!wire || typeof wire.$set !== 'function') {
                    return;
                }
                syncCoordsToForm(wire, statePrefix, lat, lng);
                const list = components || [];
                const get = function (type) {
                    const c = list.find(function (x) {
                        return x.types && x.types.indexOf(type) !== -1;
                    });
                    return c ? c.long_name : '';
                };
                const streetNumber = get('street_number');
                const route = get('route');
                const premise = get('premise') || get('subpremise');
                const line1 = [premise, streetNumber, route].filter(Boolean).join(' ').trim();
                const locality =
                    get('locality') ||
                    get('administrative_area_level_2') ||
                    get('sublocality') ||
                    get('neighborhood') ||
                    '';
                const admin1 = get('administrative_area_level_1');
                const addressLine =
                    line1 ||
                    (formattedAddress ? String(formattedAddress).split(',')[0].trim() : '') ||
                    formattedAddress ||
                    '';
                void wire.$set(
                    deliveryStateKey(statePrefix, 'delivery_address'),
                    String(addressLine).slice(0, 255),
                );
                void wire.$set(deliveryStateKey(statePrefix, 'delivery_city'), String(locality).slice(0, 100));
                void wire.$set(deliveryStateKey(statePrefix, 'delivery_state'), String(admin1).slice(0, 100));
            }

            function syncDeliveryFromNominatim(wire, statePrefix, item) {
                if (!item || !wire || typeof wire.$set !== 'function') {
                    return;
                }
                const lat = parseFloat(item.lat);
                const lng = parseFloat(item.lon);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }
                syncCoordsToForm(wire, statePrefix, lat, lng);
                const a = item.address || {};
                const line1 =
                    [a.house_number, a.road].filter(Boolean).join(' ').trim() ||
                    a.pedestrian ||
                    a.neighbourhood ||
                    '';
                const city =
                    a.city ||
                    a.town ||
                    a.village ||
                    a.municipality ||
                    a.county ||
                    '';
                const state = a.state || a.region || '';
                let addr = line1;
                if (!addr && item.display_name) {
                    addr = String(item.display_name).split(',').slice(0, 2).join(', ').trim();
                }
                void wire.$set(deliveryStateKey(statePrefix, 'delivery_address'), String(addr || '').slice(0, 255));
                void wire.$set(deliveryStateKey(statePrefix, 'delivery_city'), String(city).slice(0, 100));
                void wire.$set(deliveryStateKey(statePrefix, 'delivery_state'), String(state).slice(0, 100));
            }

            function parseInitialCoords(el) {
                const la = el.dataset.initialLat;
                const ln = el.dataset.initialLng;
                if (la === '' || ln === '') {
                    return { lat: null, lng: null };
                }
                const lat = parseFloat(la);
                const lng = parseFloat(ln);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return { lat: null, lng: null };
                }
                return { lat, lng };
            }

            let googleMapsLoadPromise = null;

            function loadGoogleMapsScript(apiKey) {
                if (window.google && window.google.maps && window.google.maps.places) {
                    return Promise.resolve();
                }
                if (googleMapsLoadPromise) {
                    return googleMapsLoadPromise;
                }
                const key = (apiKey || '').trim();
                if (key === '') {
                    return Promise.reject(new Error('Sin clave de Google Maps para el navegador.'));
                }
                googleMapsLoadPromise = new Promise(function (resolve, reject) {
                    const cb = '_filamentGmapsInit_' + String(Math.random()).slice(2);
                    window[cb] = function () {
                        try {
                            delete window[cb];
                        } catch (e) {
                            /* noop */
                        }
                        googleMapsLoadPromise = null;
                        resolve();
                    };
                    const s = document.createElement('script');
                    s.src =
                        'https://maps.googleapis.com/maps/api/js?key=' +
                        encodeURIComponent(key) +
                        '&loading=async&libraries=places&callback=' +
                        cb;
                    s.async = true;
                    s.defer = true;
                    s.onerror = function () {
                        googleMapsLoadPromise = null;
                        try {
                            delete window[cb];
                        } catch (e2) {
                            /* noop */
                        }
                        reject(new Error('No se pudo cargar el script de Google Maps.'));
                    };
                    document.head.appendChild(s);
                });
                return googleMapsLoadPromise;
            }

            function mountGoogleMap(el) {
                const shell = getDeliveryMapShell(el);
                const searchInput =
                    shell && shell.querySelector
                        ? shell.querySelector('input[data-delivery-map-search="1"]')
                        : null;
                const searchSubmitBtn =
                    shell && shell.querySelector
                        ? shell.querySelector('[data-delivery-map-search-submit]')
                        : null;
                const geolocateBtn =
                    shell && shell.querySelector
                        ? shell.querySelector('[data-delivery-map-geolocate]')
                        : null;
                const poiToolbar =
                    shell && shell.querySelector
                        ? shell.querySelector('[data-delivery-map-poi-toolbar="1"]')
                        : null;
                const proxyReverseUrl = el.getAttribute('data-nominatim-reverse-url') || '';
                const statePrefix = el.getAttribute('data-livewire-state-prefix') || 'data';

                const { lat: ilat, lng: ilng } = parseInitialCoords(el);
                const centerLat = ilat != null ? ilat : CARACAS_DEFAULT.lat;
                const centerLng = ilng != null ? ilng : CARACAS_DEFAULT.lng;
                const zoom = ilat != null && ilng != null ? 16 : 12;

                const midLight = (el.getAttribute('data-google-map-id-light') || '').trim();
                const midDark = (el.getAttribute('data-google-map-id-dark') || '').trim();

                function resolveGoogleMapId() {
                    if (midLight === '' && midDark === '') {
                        return '';
                    }
                    const isDark = document.documentElement.classList.contains('dark');
                    if (isDark) {
                        return midDark || midLight;
                    }
                    return midLight || midDark;
                }

                const mapOpts = {
                    center: { lat: centerLat, lng: centerLng },
                    zoom: zoom,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: true,
                };
                const initialMapId = resolveGoogleMapId();
                if (initialMapId !== '') {
                    mapOpts.mapId = initialMapId;
                }

                const map = new google.maps.Map(el, mapOpts);

                const veBounds = new google.maps.LatLngBounds(
                    { lat: 0.65, lng: -73.45 },
                    { lat: 12.7, lng: -59.75 },
                );

                if (midLight !== '' || midDark !== '') {
                    try {
                        const obs = new MutationObserver(function () {
                            const id = resolveGoogleMapId();
                            if (id !== '') {
                                map.setOptions({ mapId: id });
                            }
                        });
                        obs.observe(document.documentElement, {
                            attributes: true,
                            attributeFilter: ['class'],
                        });
                    } catch (obsErr) {
                        /* noop */
                    }
                }

                const geocoder = new google.maps.Geocoder();

                let marker = null;
                /** @type {google.maps.Marker[]} */
                let poiMarkers = [];

                function clearPoiMarkers() {
                    poiMarkers.forEach(function (m) {
                        m.setMap(null);
                    });
                    poiMarkers = [];
                }

                function runNearbyPlaceSearch(placeType) {
                    const wire = getWireForElement(el);
                    if (!window.google.maps.places || !placeType) {
                        setDeliveryMapStatus(el, 'Lugares cercanos no disponibles.', 'error');
                        return;
                    }
                    clearPoiMarkers();
                    const center = map.getCenter();
                    if (!center) {
                        return;
                    }
                    const svc = new google.maps.places.PlacesService(map);
                    const request = {
                        location: center,
                        radius: 3500,
                        type: placeType,
                    };
                    svc.nearbySearch(request, function (results, status) {
                        if (status === google.maps.places.PlacesServiceStatus.ZERO_RESULTS) {
                            setDeliveryMapStatus(
                                el,
                                'No hay resultados de este tipo cerca del centro del mapa. Acerca o mueve el mapa y vuelve a intentar.',
                                'info',
                            );
                            return;
                        }
                        if (status !== google.maps.places.PlacesServiceStatus.OK || !results || !results.length) {
                            setDeliveryMapStatus(el, 'No se pudieron cargar lugares cercanos.', 'error');
                            return;
                        }
                        const maxPins = 24;
                        const slice = results.slice(0, maxPins);
                        slice.forEach(function (place) {
                            if (!place.geometry || !place.geometry.location) {
                                return;
                            }
                            const pm = new google.maps.Marker({
                                position: place.geometry.location,
                                map: map,
                                title: place.name || '',
                                icon: {
                                    path: google.maps.SymbolPath.CIRCLE,
                                    scale: 6,
                                    fillColor: '#0E949A',
                                    fillOpacity: 0.92,
                                    strokeColor: '#ffffff',
                                    strokeWeight: 1.5,
                                },
                            });
                            pm.addListener('click', function () {
                                const pid = place.place_id;
                                if (!pid) {
                                    const ll = place.geometry.location;
                                    clearPoiMarkers();
                                    reverseGeocode(ll.lat(), ll.lng(), wire);
                                    return;
                                }
                                svc.getDetails(
                                    {
                                        placeId: pid,
                                        fields: ['address_components', 'formatted_address', 'geometry', 'name'],
                                    },
                                    function (det, st) {
                                        clearPoiMarkers();
                                        if (
                                            st !== google.maps.places.PlacesServiceStatus.OK ||
                                            !det ||
                                            !det.geometry ||
                                            !det.geometry.location
                                        ) {
                                            const ll = place.geometry.location;
                                            reverseGeocode(ll.lat(), ll.lng(), wire);
                                            return;
                                        }
                                        const ll = det.geometry.location;
                                        const plat = ll.lat();
                                        const plng = ll.lng();
                                        if (det.geometry.viewport) {
                                            map.fitBounds(det.geometry.viewport);
                                        } else {
                                            map.setCenter({ lat: plat, lng: plng });
                                            map.setZoom(17);
                                        }
                                        placeMarkerAndSync(plat, plng, wire, {
                                            formatted_address: det.formatted_address || det.name || '',
                                            address_components: det.address_components || [],
                                        });
                                    },
                                );
                            });
                            poiMarkers.push(pm);
                        });
                        setDeliveryMapStatus(
                            el,
                            String(slice.length) +
                                ' lugares mostrados. Pulsa un pin turquesa para fijar la entrega.',
                            'success',
                        );
                    });
                }

                if (poiToolbar) {
                    poiToolbar.addEventListener('click', function (ev) {
                        const t = ev.target;
                        if (!t || typeof t.closest !== 'function') {
                            return;
                        }
                        const clearBtn = t.closest('[data-delivery-map-clear-pois]');
                        if (clearBtn) {
                            ev.preventDefault();
                            clearPoiMarkers();
                            setDeliveryMapStatus(el, 'Pins de exploración quitados.', 'info');
                            return;
                        }
                        const chip = t.closest('[data-place-type]');
                        if (!chip) {
                            return;
                        }
                        ev.preventDefault();
                        const pt = chip.getAttribute('data-place-type') || '';
                        runNearbyPlaceSearch(pt);
                    });
                }

                function attachGoogleMarkerDrag(m) {
                    if (!m || typeof m.setDraggable !== 'function') {
                        return;
                    }
                    m.setDraggable(true);
                    m.addListener('dragend', function (e) {
                        const ll = e.latLng;
                        reverseGeocode(ll.lat(), ll.lng(), getWireForElement(el));
                    });
                }

                function reverseGeocode(lat, lng, wire) {
                    geocoder.geocode({ location: { lat: lat, lng: lng } }, function (results, status) {
                        if (status === 'OK' && results && results[0]) {
                            placeMarkerAndSync(lat, lng, wire, results[0]);
                            return;
                        }
                        if (proxyReverseUrl && wire) {
                            const url =
                                proxyReverseUrl +
                                (proxyReverseUrl.indexOf('?') === -1 ? '?' : '&') +
                                'lat=' +
                                encodeURIComponent(lat) +
                                '&lng=' +
                                encodeURIComponent(lng);
                            fetch(url, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            })
                                .then(function (r) {
                                    return r.json();
                                })
                                .then(function (item) {
                                    if (item && item.address) {
                                        placeMarkerAndSync(lat, lng, wire, null);
                                        syncDeliveryFromNominatim(wire, statePrefix, item);
                                    } else {
                                        placeMarkerAndSync(lat, lng, wire, null);
                                    }
                                })
                                .catch(function () {
                                    placeMarkerAndSync(lat, lng, wire, null);
                                });
                            return;
                        }
                        placeMarkerAndSync(lat, lng, wire, null);
                    });
                }

                function placeMarkerAndSync(lat, lng, wire, geocodeResult) {
                    setDeliveryMapStatus(el, '', 'info');
                    if (marker) {
                        marker.setMap(null);
                    }
                    marker = new google.maps.Marker({
                        position: { lat: lat, lng: lng },
                        map: map,
                    });
                    attachGoogleMarkerDrag(marker);
                    map.panTo({ lat: lat, lng: lng });
                    if (geocodeResult) {
                        applyGoogleAddressComponents(
                            wire,
                            statePrefix,
                            geocodeResult.formatted_address,
                            geocodeResult.address_components,
                            lat,
                            lng,
                        );
                    } else {
                        syncCoordsToForm(wire, statePrefix, lat, lng);
                    }
                }

                function runForwardGeocodeFromSearch() {
                    if (!searchInput) {
                        return;
                    }
                    const q = String(searchInput.value || '').trim();
                    if (q === '') {
                        setDeliveryMapStatus(el, 'Escribe una dirección o elige una sugerencia.', 'info');
                        return;
                    }
                    clearPoiMarkers();
                    const wire = getWireForElement(el);
                    geocoder.geocode(
                        {
                            address: q,
                            bounds: map.getBounds() || veBounds,
                            componentRestrictions: { country: 'VE' },
                        },
                        function (results, status) {
                            if (status === 'OK' && results && results[0] && results[0].geometry) {
                                const loc = results[0].geometry.location;
                                const plat = loc.lat();
                                const plng = loc.lng();
                                if (results[0].geometry.viewport) {
                                    map.fitBounds(results[0].geometry.viewport);
                                } else {
                                    map.setCenter({ lat: plat, lng: plng });
                                    map.setZoom(17);
                                }
                                placeMarkerAndSync(plat, plng, wire, results[0]);
                                setDeliveryMapStatus(el, '', 'info');
                                return;
                            }
                            setDeliveryMapStatus(
                                el,
                                'No se encontró esa ubicación. Prueba otra referencia o elige una sugerencia de la lista.',
                                'error',
                            );
                        },
                    );
                }

                if (searchInput && google.maps.places && google.maps.places.Autocomplete) {
                    const autocomplete = new google.maps.places.Autocomplete(searchInput, {
                        fields: ['address_components', 'formatted_address', 'geometry', 'name'],
                        componentRestrictions: { country: 've' },
                    });
                    autocomplete.bindTo('bounds', map);
                    map.addListener('bounds_changed', function () {
                        const b = map.getBounds();
                        if (b) {
                            autocomplete.setBounds(b);
                        }
                    });
                    autocomplete.addListener('place_changed', function () {
                        const place = autocomplete.getPlace();
                        if (!place.geometry || !place.geometry.location) {
                            return;
                        }
                        clearPoiMarkers();
                        const ll = place.geometry.location;
                        const plat = ll.lat();
                        const plng = ll.lng();
                        const wire = getWireForElement(el);
                        if (place.geometry.viewport) {
                            map.fitBounds(place.geometry.viewport);
                        } else {
                            map.setCenter({ lat: plat, lng: plng });
                            map.setZoom(17);
                        }
                        placeMarkerAndSync(plat, plng, wire, {
                            formatted_address: place.formatted_address || place.name || '',
                            address_components: place.address_components || [],
                        });
                    });
                }

                if (searchInput) {
                    searchInput.addEventListener('keydown', function (ev) {
                        if (ev.key === 'Enter') {
                            ev.preventDefault();
                            ev.stopPropagation();
                            runForwardGeocodeFromSearch();
                        }
                    });
                }
                if (searchSubmitBtn) {
                    searchSubmitBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        runForwardGeocodeFromSearch();
                    });
                }
                if (geolocateBtn && navigator.geolocation) {
                    geolocateBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        setDeliveryMapStatus(el, 'Obteniendo tu ubicación…', 'info');
                        navigator.geolocation.getCurrentPosition(
                            function (pos) {
                                clearPoiMarkers();
                                const plat = pos.coords.latitude;
                                const plng = pos.coords.longitude;
                                map.setCenter({ lat: plat, lng: plng });
                                map.setZoom(16);
                                reverseGeocode(plat, plng, getWireForElement(el));
                                setDeliveryMapStatus(el, '', 'info');
                            },
                            function () {
                                setDeliveryMapStatus(
                                    el,
                                    'Activa el permiso de ubicación en el navegador o marca el punto en el mapa.',
                                    'error',
                                );
                            },
                            { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 },
                        );
                    });
                } else if (geolocateBtn) {
                    geolocateBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        setDeliveryMapStatus(el, 'Tu navegador no permite geolocalización.', 'error');
                    });
                }

                if (ilat != null && ilng != null) {
                    marker = new google.maps.Marker({
                        position: { lat: ilat, lng: ilng },
                        map: map,
                    });
                    attachGoogleMarkerDrag(marker);
                }

                map.addListener('click', function (e) {
                    clearPoiMarkers();
                    const lat = e.latLng.lat();
                    const lng = e.latLng.lng();
                    reverseGeocode(lat, lng, getWireForElement(el));
                });

                el.__googleMap = map;

                function resize() {
                    try {
                        google.maps.event.trigger(map, 'resize');
                        map.setCenter({ lat: centerLat, lng: centerLng });
                    } catch (e) {
                        /* noop */
                    }
                }
                setTimeout(resize, 100);
                setTimeout(resize, 500);
            }

            function nominatimReverse(lat, lng, wire, statePrefix, onText, proxyReverseUrl) {
                const url = proxyReverseUrl
                    ? proxyReverseUrl +
                      (proxyReverseUrl.indexOf('?') === -1 ? '?' : '&') +
                      'lat=' +
                      encodeURIComponent(lat) +
                      '&lng=' +
                      encodeURIComponent(lng)
                    : 'https://nominatim.openstreetmap.org/reverse?format=json&addressdetails=1&lat=' +
                      lat +
                      '&lon=' +
                      lng;
                fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (item) {
                        if (item && item.address) {
                            syncDeliveryFromNominatim(wire, statePrefix, item);
                            if (typeof onText === 'function') {
                                onText(item.display_name || '');
                            }
                        }
                    })
                    .catch(function () {
                        syncCoordsToForm(wire, statePrefix, lat, lng);
                    });
            }

            function mountCanvas(el) {
                if (el.getAttribute('data-map-mounted') === '1') {
                    return;
                }
                if (el.getAttribute('data-map-pending') === '1') {
                    return;
                }
                el.setAttribute('data-map-pending', '1');

                const key = (el.getAttribute('data-google-maps-key') || '').trim();
                if (key === '') {
                    el.removeAttribute('data-map-pending');
                    return;
                }

                loadGoogleMapsScript(key)
                    .then(function () {
                        mountGoogleMap(el);
                        el.setAttribute('data-map-mounted', '1');
                        el.removeAttribute('data-map-pending');
                    })
                    .catch(function (err) {
                        console.error('[delivery-map-picker]', err);
                        el.removeAttribute('data-map-pending');
                        el.innerHTML =
                            '<p class="p-4 text-sm text-red-600 dark:text-red-400">No se pudo cargar Google Maps. Revisa la clave, Maps JavaScript API, Places API y la restricción por referrer.</p>';
                    });
            }

            function scanAndMount() {
                document
                    .querySelectorAll('[data-delivery-map-canvas]:not([data-map-mounted]):not([data-map-pending])')
                    .forEach(function (el) {
                        mountCanvas(el);
                    });
            }

            function boot() {
                scanAndMount();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }

            document.addEventListener('livewire:initialized', boot);
            document.addEventListener('livewire:navigated', boot);
            document.addEventListener('livewire:morph.added', boot);
        })();
    </script>
@endonce
