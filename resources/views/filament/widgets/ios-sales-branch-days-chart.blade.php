@php
    use App\Filament\Widgets\Support\BrandChartPalette;
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $isDayOverview = $this->isDayOverviewMode();
    $maxHeight = $this->getMaxHeight();
    $chartStageKey = ($this->filter ?? 'month').'-'.($this->drillDownDate ?? 'overview');
    $pollingInterval = $this->getPollingInterval();
    $drillDownBranches = $isDayOverview ? [] : $this->getDrillDownBranches();
    $drillDownBcvRate = $isDayOverview ? null : $this->getDrillDownBcvRateFormatted();
    $drillDownBranchFills = BrandChartPalette::branchBarFills(max(1, count($drillDownBranches)));
@endphp

<x-filament-widgets::widget
    class="fi-wi-chart fi-farmaadmin-ios-sales-trend-chart fi-farmaadmin-ios-sales-branch-days-chart"
>
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <x-slot name="afterHeader">
            <div class="fi-ios-sales-branch-days-chart__header-actions">
                @if (! $isDayOverview)
                    <button
                        type="button"
                        wire:click="backToDayOverview"
                        wire:loading.attr="disabled"
                        wire:target="backToDayOverview, drillIntoDay"
                        class="fi-ios-sales-branch-days-chart__back-btn"
                    >
                        <x-filament::icon
                            icon="heroicon-m-chevron-left"
                            class="fi-ios-sales-branch-days-chart__back-icon"
                        />
                        <span>Volver al mes</span>
                    </button>
                @endif

                @if ($filters)
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filter, drillIntoDay, backToDayOverview"
                        @class([
                            'fi-wi-chart-filter',
                            'fi-ios-sales-branch-days-chart__filter',
                            'fi-ios-sales-branch-days-chart__filter--compact' => ! $isDayOverview,
                        ])
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filter"
                            :disabled="! $isDayOverview"
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
            wire:target="filter, drillIntoDay, backToDayOverview"
            @if (filled($pollingInterval))
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                @class([
                    'fi-ios-sales-branch-days-chart__chart-shell',
                    'fi-ios-sales-branch-days-chart__chart-shell--drill' => ! $isDayOverview,
                    'fi-ios-sales-branch-days-chart__chart-shell--overview' => $isDayOverview,
                ])
                wire:key="branch-sales-days-shell-{{ $chartStageKey }}"
            >
                @if ($isDayOverview)
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
                                        return typeof value === 'number' && value > 0;
                                    });

                                    if (! hasSales) {
                                        return;
                                    }

                                    $wire.drillIntoDay(index);
                                };

                                chart.options.onHover = (event, elements) => {
                                    const target = event.native?.target;
                                    if (! target) {
                                        return;
                                    }

                                    const hasSales = elements?.some((element) => {
                                        const value = chart.data.datasets[element.datasetIndex]?.data[element.index];
                                        return typeof value === 'number' && value > 0;
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

                    @if ($drillDownBranches !== [])
                        <div class="fi-ios-sales-payment-breakdown">
                            @if (filled($drillDownBcvRate))
                                <p class="fi-ios-sales-payment-breakdown__bcv">
                                    <x-filament::icon
                                        icon="heroicon-m-banknotes"
                                        class="fi-ios-sales-payment-breakdown__bcv-icon"
                                    />
                                    <span>Tasa BCV del día: <strong>{{ $drillDownBcvRate }}</strong></span>
                                </p>
                            @endif

                            <div class="fi-ios-sales-payment-breakdown__branches">
                                @foreach ($drillDownBranches as $branchIndex => $branch)
                                    <section class="fi-ios-sales-payment-breakdown__branch">
                                        <header
                                            class="fi-ios-sales-payment-breakdown__branch-header"
                                            style="--payment-swatch: {{ $drillDownBranchFills[$branchIndex] ?? 'rgba(14, 148, 154, 0.82)' }}"
                                        >
                                            <span class="fi-ios-sales-payment-breakdown__swatch" aria-hidden="true"></span>
                                            <span class="fi-ios-sales-payment-breakdown__branch-name">
                                                {{ $branch['branch_name'] }}
                                            </span>
                                            <span class="fi-ios-sales-payment-breakdown__branch-total">
                                                ${{ number_format((float) ($branch['branch_total_usd'] ?? 0), 2, ',', '.') }}
                                            </span>
                                        </header>

                                        @if (($branch['methods'] ?? []) !== [])
                                            <ul class="fi-ios-sales-payment-breakdown__list" role="list">
                                                @foreach ($branch['methods'] as $method)
                                                    <li class="fi-ios-sales-payment-breakdown__item">
                                                        <span class="fi-ios-sales-payment-breakdown__label">
                                                            {{ $method['legend_label'] }}
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="fi-ios-sales-payment-breakdown__empty">
                                                Sin cobros registrados este día.
                                            </p>
                                        @endif
                                    </section>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                <div
                    wire:loading.flex
                    wire:target="filter, drillIntoDay, backToDayOverview"
                    class="fi-ios-sales-branch-days-chart__loader"
                >
                    <x-filament::loading-indicator class="fi-ios-sales-branch-days-chart__loader-icon" />
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
