{{--
    Selector de rango del portal (mismo comportamiento que el del dashboard): dos meses, un toque para
    "desde" y otro para "hasta". Las fechas se guardan en Livewire sin ir al servidor hasta enviar.
    Lógica: resources/js/app.js → employeeDateRangePicker.
--}}
<div
    class="ep-range"
    x-data="employeeDateRangePicker(@js($config))"
    x-on:keydown.escape.window="close()"
    x-on:click.outside="close()"
>
    <button
        type="button"
        class="ep-range__trigger ep-glass"
        x-bind:class="{ 'is-open': open, 'is-empty': ! from }"
        x-on:click="toggle()"
        x-bind:aria-expanded="open"
        aria-haspopup="dialog"
    >
        <span class="ep-range__trigger-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
        </span>
        <span class="ep-range__trigger-copy">
            <span class="ep-range__trigger-label">{{ $label ?? 'Fechas' }}</span>
            <strong x-text="from ? formatRange(from, to) : 'Elige desde y hasta'"></strong>
        </span>
        <span class="ep-range__trigger-count" x-show="from" x-text="countLabel(from, to)"></span>
    </button>

    <div
        class="ep-range__panel ep-glass"
        x-show="open"
        x-cloak
        x-transition.opacity.scale.origin.top.duration.180ms
        role="dialog"
        aria-label="Seleccionar rango de fechas"
    >
        @if (! empty($presets))
            <div class="ep-range__presets" role="group" aria-label="Rangos rápidos">
                @foreach ($presets as $preset)
                    <button
                        type="button"
                        class="ep-range__preset"
                        x-bind:class="{ 'is-active': from === @js($preset['from']) && to === @js($preset['to']) }"
                        x-on:click="applyRange(@js($preset['from']), @js($preset['to']))"
                    >{{ $preset['label'] }}</button>
                @endforeach
            </div>
        @endif

        <div class="ep-range__calendars">
            <button type="button" class="ep-range__nav ep-range__nav--prev" x-on:click="shiftMonths(-1)" x-bind:disabled="! canGoPrev" aria-label="Mes anterior">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </button>
            <button type="button" class="ep-range__nav ep-range__nav--next" x-on:click="shiftMonths(1)" x-bind:disabled="! canGoNext" aria-label="Mes siguiente">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>

            <template x-for="month in months" x-bind:key="month.key">
                <div class="ep-range__month">
                    <p class="ep-range__month-title" x-text="month.label"></p>
                    <div class="ep-range__weekdays" aria-hidden="true">
                        <template x-for="weekday in weekdays" x-bind:key="weekday"><span x-text="weekday"></span></template>
                    </div>
                    <div class="ep-range__days" x-on:mouseleave="hoverDate = null">
                        <template x-for="cell in month.cells" x-bind:key="cell.key">
                            <button
                                type="button"
                                class="ep-range__day"
                                x-bind:class="dayClasses(cell)"
                                x-bind:disabled="cell.date === null || isDisabled(cell.date)"
                                x-bind:tabindex="cell.date === null ? -1 : 0"
                                x-bind:aria-label="cell.ariaLabel"
                                x-on:click="selectDay(cell.date)"
                                x-on:mouseenter="hoverDate = cell.date"
                                x-on:focus="hoverDate = cell.date"
                            ><span x-text="cell.day"></span></button>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <p class="ep-range__hint">
            <span x-text="selecting ? 'Elige la fecha final' : 'Elige la fecha inicial'"></span>
            <strong x-text="draftLabel"></strong>
        </p>
    </div>

    <p class="ep-range__warning" x-show="isEarly()" x-cloak>
        Son vacaciones anticipadas: tu derecho nace el <strong x-text="formatDay(rightStartsOn)"></strong>. RR. HH. igual puede aprobarlas.
    </p>
    <p class="ep-range__warning ep-range__warning--danger" x-show="exceedsEntitlement()" x-cloak>
        Elegiste <strong x-text="count(from, to)"></strong> días hábiles y te corresponden <strong x-text="entitledDays"></strong>.
    </p>
</div>
