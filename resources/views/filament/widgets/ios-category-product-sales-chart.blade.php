@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $isCategoryOverview = $this->isCategoryOverviewMode();
    $maxHeight = $this->getMaxHeight();
    $chartStageKey = ($this->filter ?? 'month').'-'.($this->drillDownCategoryId ?? 'overview');
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    class="fi-wi-chart fi-farmaadmin-ios-sales-trend-chart fi-farmaadmin-ios-sales-branch-days-chart fi-farmaadmin-ios-category-product-sales-chart"
>
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <x-slot name="afterHeader">
            <div class="fi-ios-sales-branch-days-chart__header-actions">
                @if (! $isCategoryOverview)
                    <button
                        type="button"
                        wire:click="backToCategoryOverview"
                        wire:loading.attr="disabled"
                        wire:target="backToCategoryOverview, drillIntoCategory"
                        class="fi-ios-sales-branch-days-chart__back-btn"
                    >
                        <x-filament::icon
                            icon="heroicon-m-chevron-left"
                            class="fi-ios-sales-branch-days-chart__back-icon"
                        />
                        <span>Volver a categorías</span>
                    </button>
                @endif

                @if ($filters)
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filter, drillIntoCategory, backToCategoryOverview"
                        @class([
                            'fi-wi-chart-filter',
                            'fi-ios-sales-branch-days-chart__filter',
                            'fi-ios-sales-branch-days-chart__filter--compact' => ! $isCategoryOverview,
                        ])
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filter"
                            :disabled="! $isCategoryOverview"
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
            class="fi-ios-sales-branch-days-chart__stage"
            wire:loading.class="fi-ios-sales-branch-days-chart__stage--loading"
            wire:target="filter, drillIntoCategory, backToCategoryOverview"
            @if (filled($pollingInterval))
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                @class([
                    'fi-ios-sales-branch-days-chart__chart-shell',
                    'fi-ios-sales-branch-days-chart__chart-shell--drill' => ! $isCategoryOverview,
                    'fi-ios-sales-branch-days-chart__chart-shell--overview' => $isCategoryOverview,
                ])
                wire:key="category-product-sales-shell-{{ $chartStageKey }}"
            >
                @if ($isCategoryOverview)
                    <div
                        x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
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
                                    const hasSales = elements.some((element) => {
                                        const value = chart.data.datasets[element.datasetIndex]?.data[element.index];
                                        return Number(value) > 0;
                                    });

                                    if (! hasSales) {
                                        return;
                                    }

                                    $wire.drillIntoCategory(index);
                                };

                                chart.options.onHover = (event, elements) => {
                                    const target = event.native?.target;
                                    if (! target) {
                                        return;
                                    }

                                    const hasSales = elements?.some((element) => {
                                        const value = chart.data.datasets[element.datasetIndex]?.data[element.index];
                                        return Number(value) > 0;
                                    });

                                    target.style.cursor = hasSales ? 'pointer' : 'default';
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
                                    'fi-ios-sales-branch-days-chart__canvas',
                                    'fi-ios-sales-branch-days-chart__canvas--enter',
                                ])
                        }}
                    >
                        @include('filament.widgets.partials.ios-sales-chart-canvas', ['maxHeight' => $maxHeight])
                    </div>
                @else
                    <div
                        x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
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
                                    'fi-ios-sales-branch-days-chart__canvas',
                                    'fi-ios-sales-branch-days-chart__canvas--enter',
                                ])
                        }}
                    >
                        @include('filament.widgets.partials.ios-sales-chart-canvas', ['maxHeight' => $maxHeight])
                    </div>
                @endif

                <div
                    wire:loading.flex
                    wire:target="filter, drillIntoCategory, backToCategoryOverview"
                    class="fi-ios-sales-branch-days-chart__loader"
                >
                    <x-filament::loading-indicator class="fi-ios-sales-branch-days-chart__loader-icon" />
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
