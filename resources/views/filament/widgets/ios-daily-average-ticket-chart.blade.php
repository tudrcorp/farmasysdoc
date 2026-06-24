@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight();
    $chartStageKey = now()->format('Y-m');
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    class="fi-wi-chart fi-farmaadmin-ios-sales-trend-chart fi-farmaadmin-ios-sales-branch-days-chart fi-farmaadmin-ios-daily-average-ticket-chart"
>
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <div
            class="fi-ios-sales-branch-days-chart__stage"
            wire:loading.class="fi-ios-sales-branch-days-chart__stage--loading"
            wire:target="updateChartData"
            @if (filled($pollingInterval))
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                class="fi-ios-sales-branch-days-chart__chart-shell fi-ios-sales-branch-days-chart__chart-shell--overview"
                wire:key="daily-average-ticket-shell-{{ $chartStageKey }}"
            >
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
                        const attachAverageTicketTooltip = () => {
                            const chart = getChart();
                            if (! chart) {
                                return;
                            }

                            const formatUsd = (value) => '$' + Number(value ?? 0).toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            });

                            chart.options.plugins.tooltip.callbacks.label = (context) => {
                                const branchLabel = context.dataset.branchName ?? context.dataset.label ?? '';
                                const average = context.parsed?.y ?? 0;
                                const total = context.dataset.dailyTotals?.[context.dataIndex] ?? 0;
                                const clients = context.dataset.customerCounts?.[context.dataIndex] ?? 0;

                                return branchLabel
                                    + ' · Ticket ' + formatUsd(average)
                                    + ' · Ventas ' + formatUsd(total)
                                    + ' · Clientes ' + Number(clients).toLocaleString('es-VE');
                            };

                            chart.update('none');
                        };

                        $nextTick(attachAverageTicketTooltip);
                        $wire.$on('updateChartData', () => $nextTick(attachAverageTicketTooltip));
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
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
