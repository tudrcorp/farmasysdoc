<?php

namespace App\Filament\BusinessPartners\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Marketing\MarketingBarChartStyle;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Gráfico mixto (año en curso, solo finalizados): barras = suma US$ del total del pedido por mes;
 * línea = cantidad de pedidos finalizados por mes. Doble eje Y. Vista iOS como ventas Farmaadmin.
 */
class TotalOrderForMonthChart extends ChartWidget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-sales-chart';

    protected static ?int $sort = 0;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Pedidos finalizados por mes';

    protected ?string $description = 'Barras: suma en US$ del total de cada pedido. Línea: número de pedidos en «Finalizado» (su compañía).';

    protected ?string $maxHeight = '320px';

    protected string $color = 'info';

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        if (! Filament::auth()->check()) {
            return false;
        }

        $user = Filament::auth()->user();

        return $user instanceof User && $user->isPartnerCompanyUser();
    }

    public function updateChartData(): void
    {
        $this->cachedData = null;

        parent::updateChartData();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array{labels: list<string>, amounts: list<float>, counts: list<int>}
     */
    private function monthlyCompletedMetricsForPartner(int $partnerCompanyId, int $year): array
    {
        $labels = [];
        $amounts = [];
        $counts = [];

        $locale = app()->getLocale();

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $labels[] = ucfirst($start->locale($locale)->translatedFormat('M'));

            $base = Order::query()
                ->where('partner_company_id', $partnerCompanyId)
                ->where('status', OrderStatus::Completed->value)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month);

            $amounts[] = round((float) (clone $base)->sum('total'), 2);
            $counts[] = (int) (clone $base)->count();
        }

        return ['labels' => $labels, 'amounts' => $amounts, 'counts' => $counts];
    }

    private static function formatUsd(float $amount): string
    {
        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || ! $user->isPartnerCompanyUser()) {
            return [
                'datasets' => [
                    [
                        'type' => 'bar',
                        'yAxisID' => 'y',
                        'label' => __('Finalizados · Total US$ 0,00'),
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                    [
                        'type' => 'line',
                        'yAxisID' => 'y1',
                        'label' => __('Pedidos · Total 0'),
                        'data' => [],
                        'borderColor' => 'rgb(24, 172, 178)',
                    ],
                ],
                'labels' => [],
            ];
        }

        $year = (int) now()->year;
        $chart = $this->monthlyCompletedMetricsForPartner((int) $user->partner_company_id, $year);
        $n = count($chart['amounts']);
        $yearTotalUsd = round(array_sum($chart['amounts']), 2);
        $yearTotalOrders = array_sum($chart['counts']);

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'yAxisID' => 'y',
                    'label' => __('Suma US$ :year · Total :amount', [
                        'year' => (string) $year,
                        'amount' => self::formatUsd($yearTotalUsd),
                    ]),
                    'data' => $chart['amounts'],
                    'backgroundColor' => IosSalesTrendChartStyle::vividBarFills($n),
                    'hoverBackgroundColor' => IosSalesTrendChartStyle::vividBarHovers($n),
                    'borderColor' => IosSalesTrendChartStyle::barBorderColors($n),
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.5)',
                    'borderWidth' => 1,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'type' => 'line',
                    'yAxisID' => 'y1',
                    'label' => __('Cantidad pedidos :year · Total :n', [
                        'year' => (string) $year,
                        'n' => (string) $yearTotalOrders,
                    ]),
                    'data' => $chart['counts'],
                    'borderColor' => 'rgb(24, 172, 178)',
                    'backgroundColor' => 'rgba(24, 172, 178, 0.12)',
                    'borderWidth' => 2.5,
                    'tension' => 0.35,
                    'fill' => false,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'pointBackgroundColor' => 'rgb(24, 172, 178)',
                    'pointBorderColor' => 'rgba(255, 255, 255, 0.95)',
                    'pointBorderWidth' => 2,
                    'order' => 1,
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $scale = MarketingBarChartStyle::scaleStyling();

        /*
         * Sin RawJs en opciones (serialización @js() del ChartWidget).
         * y = US$ (izquierda), y1 = conteo entero (derecha, sin rejilla sobre el área).
         */
        return array_replace_recursive(
            IosSalesTrendChartStyle::verticalChartOptions(),
            [
                'scales' => [
                    'y' => [
                        'position' => 'left',
                        'beginAtZero' => true,
                    ],
                    'y1' => array_replace_recursive($scale, [
                        'position' => 'right',
                        'beginAtZero' => true,
                        'grid' => [
                            'drawOnChartArea' => false,
                        ],
                        'ticks' => [
                            'maxTicksLimit' => 8,
                        ],
                    ]),
                ],
            ]
        );
    }
}
