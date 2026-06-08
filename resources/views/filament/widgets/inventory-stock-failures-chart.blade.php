@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $isBranchOverview = $this->isBranchOverviewMode();
    $maxHeight = $this->getMaxHeight();
    $chartStageKey = ($this->filter ?? 'all').'-'.($this->drillDownBranchId ?? 'overview');
    $pollingInterval = $this->getPollingInterval();
    $chartAlpineSrc = \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets');
@endphp

<x-filament-widgets::widget
    class="fi-wi-chart fi-farmaadmin-ios-sales-trend-chart fi-farmaadmin-ios-inventory-failures-chart"
>
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <x-slot name="afterHeader">
            <div class="fi-ios-inventory-failures-chart__header-actions">
                @if (! $isBranchOverview)
                    <button
                        type="button"
                        wire:click="backToBranchOverview"
                        wire:loading.attr="disabled"
                        wire:target="backToBranchOverview, drillIntoBranch, filter"
                        class="fi-ios-inventory-failures-chart__back-btn"
                    >
                        <x-filament::icon
                            icon="heroicon-m-chevron-left"
                            class="fi-ios-inventory-failures-chart__back-icon"
                        />
                        <span>Volver a sucursales</span>
                    </button>
                @endif

                @if ($filters)
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filter, drillIntoBranch, backToBranchOverview"
                        class="fi-wi-chart-filter fi-ios-inventory-failures-chart__filter"
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filter"
                        >
                            @foreach ($filters as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif
            </div>
        </x-slot>

        <div
            class="fi-ios-inventory-failures-chart__stage"
            wire:loading.class="fi-ios-inventory-failures-chart__stage--loading"
            wire:target="filter, drillIntoBranch, backToBranchOverview"
            @if (filled($pollingInterval))
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                @class([
                    'fi-ios-inventory-failures-chart__chart-shell',
                    'fi-ios-inventory-failures-chart__chart-shell--drill' => ! $isBranchOverview,
                    'fi-ios-inventory-failures-chart__chart-shell--overview' => $isBranchOverview,
                ])
                wire:key="inventory-failures-chart-shell-{{ $chartStageKey }}"
            >
                @if ($isBranchOverview)
                    <div
                        x-load
                        x-load-src="{{ $chartAlpineSrc }}"
                        wire:ignore
                        data-chart-type="{{ $type }}"
                        x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            maxHeight: @js($maxHeight),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                        x-init="
                            const attachDrillHandler = () => {
                                const chart = getChart();

                                if (! chart) {
                                    return;
                                }

                                chart.options.onClick = (event, elements) => {
                                    if (! elements?.length) {
                                        return;
                                    }

                                    const index = elements[0].index;
                                    const value = chart.data.datasets[elements[0].datasetIndex]?.data[index];

                                    if (typeof value !== 'number' || value <= 0) {
                                        return;
                                    }

                                    $wire.drillIntoBranch(index);
                                };

                                chart.options.onHover = (event, elements) => {
                                    const target = event.native?.target;

                                    if (! target) {
                                        return;
                                    }

                                    const hasFailures = elements?.some((element) => {
                                        const value = chart.data.datasets[element.datasetIndex]?.data[element.index];

                                        return typeof value === 'number' && value > 0;
                                    });

                                    target.style.cursor = hasFailures ? 'pointer' : 'default';
                                };

                                chart.update('none');
                            };

                            $nextTick(attachDrillHandler);
                            $wire.$on('updateChartData', () => $nextTick(attachDrillHandler));
                        "
                        {{
                            (new ComponentAttributeBag)
                                ->color(ChartWidgetComponent::class, $color)
                                ->class([
                                    'fi-wi-chart-canvas-ctn',
                                    'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight),
                                    'fi-ios-inventory-failures-chart__canvas',
                                    'fi-ios-inventory-failures-chart__canvas--enter',
                                ])
                        }}
                    >
                        @include('filament.widgets.partials.ios-sales-chart-canvas', ['maxHeight' => $maxHeight])
                    </div>
                @else
                    <div
                        x-load
                        x-load-src="{{ $chartAlpineSrc }}"
                        wire:ignore
                        data-chart-type="{{ $type }}"
                        x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            maxHeight: @js($maxHeight),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                        {{
                            (new ComponentAttributeBag)
                                ->color(ChartWidgetComponent::class, $color)
                                ->class([
                                    'fi-wi-chart-canvas-ctn',
                                    'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight),
                                    'fi-ios-inventory-failures-chart__canvas',
                                    'fi-ios-inventory-failures-chart__canvas--enter',
                                ])
                        }}
                    >
                        @include('filament.widgets.partials.ios-sales-chart-canvas', ['maxHeight' => $maxHeight])
                    </div>
                @endif

                <div
                    wire:loading.flex
                    wire:target="filter, drillIntoBranch, backToBranchOverview"
                    class="fi-ios-inventory-failures-chart__loader"
                >
                    <x-filament::loading-indicator class="fi-ios-inventory-failures-chart__loader-icon" />
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
