{{-- Selector de rango del dashboard: dos meses, un clic para "desde" y otro para "hasta". Lógica: dashboard-date-range-picker-script. --}}
<div
    class="fi-farmaadmin-date-range"
    x-data="farmaadminDateRangePicker({ today: @js($todayKey), maxDays: @js($maxRangeDays) })"
    x-on:keydown.escape.window="close()"
    x-on:resize.window="reposition()"
    x-on:scroll.window="reposition()"
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-bind:aria-expanded="open"
        aria-haspopup="dialog"
        wire:loading.attr="disabled"
        wire:target="applyDashboardDateRange"
        @class([
            'fi-farmaadmin-date-range__trigger',
            'fi-farmaadmin-date-range__trigger--custom' => ! $dateRangeIsDefault,
        ])
        x-bind:class="{ 'fi-farmaadmin-date-range__trigger--open': open }"
    >
        <span class="fi-farmaadmin-date-range__trigger-label">Período</span>

        <span class="fi-farmaadmin-date-range__trigger-icon" aria-hidden="true">
            <span wire:loading.remove wire:target="applyDashboardDateRange">
                <x-filament::icon icon="heroicon-m-calendar-days" class="size-4" />
            </span>
            <span wire:loading wire:target="applyDashboardDateRange">
                <x-filament::loading-indicator class="size-4" />
            </span>
        </span>

        <span class="fi-farmaadmin-date-range__trigger-value">{{ $dateRangeLabel }}</span>

        <x-filament::icon
            icon="heroicon-m-chevron-down"
            class="fi-farmaadmin-date-range__trigger-chevron size-4"
        />
    </button>

    <template x-teleport="body">
        <div
            x-cloak
            x-ref="popover"
            x-show="open"
            x-transition:enter="fi-farmaadmin-date-range__popover--animating"
            x-transition:enter-start="fi-farmaadmin-date-range__popover--hidden"
            x-transition:enter-end="fi-farmaadmin-date-range__popover--visible"
            x-transition:leave="fi-farmaadmin-date-range__popover--animating"
            x-transition:leave-start="fi-farmaadmin-date-range__popover--visible"
            x-transition:leave-end="fi-farmaadmin-date-range__popover--hidden"
            x-on:click.outside="closeFromOutside($event)"
            x-bind:style="popoverStyle"
            class="fi-farmaadmin-date-range__popover"
            role="dialog"
            aria-label="Seleccionar rango de fechas"
        >
            <div class="fi-farmaadmin-date-range__presets" role="group" aria-label="Rangos rápidos">
                @foreach ($dateRangePresets as $preset)
                    <button
                        type="button"
                        class="fi-farmaadmin-date-range__preset"
                        x-bind:class="{ 'fi-farmaadmin-date-range__preset--active': isApplied(@js($preset['from']), @js($preset['to'])) }"
                        x-on:click="applyRange(@js($preset['from']), @js($preset['to']))"
                    >
                        {{ $preset['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="fi-farmaadmin-date-range__body">
                <div class="fi-farmaadmin-date-range__calendars">
                    <button
                        type="button"
                        class="fi-farmaadmin-date-range__nav fi-farmaadmin-date-range__nav--prev"
                        x-on:click="shiftMonths(-1)"
                        aria-label="Mes anterior"
                    >
                        <x-filament::icon icon="heroicon-m-chevron-left" class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="fi-farmaadmin-date-range__nav fi-farmaadmin-date-range__nav--next"
                        x-on:click="shiftMonths(1)"
                        x-bind:disabled="! canGoNext"
                        aria-label="Mes siguiente"
                    >
                        <x-filament::icon icon="heroicon-m-chevron-right" class="size-4" />
                    </button>

                    <template x-for="month in months" x-bind:key="month.key">
                        <div class="fi-farmaadmin-date-range__month">
                            <p class="fi-farmaadmin-date-range__month-title" x-text="month.label"></p>

                            <div class="fi-farmaadmin-date-range__weekdays" aria-hidden="true">
                                <template x-for="weekday in weekdays" x-bind:key="weekday">
                                    <span x-text="weekday"></span>
                                </template>
                            </div>

                            <div class="fi-farmaadmin-date-range__days" x-on:mouseleave="hoverDate = null">
                                <template x-for="cell in month.cells" x-bind:key="cell.key">
                                    <button
                                        type="button"
                                        class="fi-farmaadmin-date-range__day"
                                        x-bind:class="dayClasses(cell)"
                                        x-bind:disabled="cell.date === null || isDisabled(cell.date)"
                                        x-bind:tabindex="cell.date === null ? -1 : 0"
                                        x-bind:aria-label="cell.date === null ? null : cell.ariaLabel"
                                        x-on:click="selectDay(cell.date)"
                                        x-on:mouseenter="hoverDate = cell.date"
                                        x-on:focus="hoverDate = cell.date"
                                    >
                                        <span class="fi-farmaadmin-date-range__day-number" x-text="cell.day"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="fi-farmaadmin-date-range__footer">
                    <p class="fi-farmaadmin-date-range__hint">
                        <span
                            class="fi-farmaadmin-date-range__hint-step"
                            x-text="selecting ? 'Elige la fecha final' : 'Elige la fecha inicial'"
                        ></span>
                        <span class="fi-farmaadmin-date-range__hint-value" x-text="draftLabel"></span>
                    </p>

                    <button
                        type="button"
                        class="fi-farmaadmin-date-range__reset"
                        x-on:click="resetToMonth()"
                    >
                        Volver a este mes
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
