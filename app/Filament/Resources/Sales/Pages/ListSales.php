<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\Actions\CashRegisterAction;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Listado de Ventas';

    /**
     * Controla la visibilidad del carril de pestañas por forma de pago (el asa superior lo alterna).
     */
    public bool $showSalesPaymentMethodTabs = true;

    public function toggleSalesPaymentMethodTabs(): void
    {
        $this->showSalesPaymentMethodTabs = ! $this->showSalesPaymentMethodTabs;
    }

    /**
     * @var list<string>
     */
    private const PAYMENT_METHOD_KEYS = [
        'transfer_usd',
        'transfer_ves',
        'pago_movil',
        'zelle',
        'efectivo_usd',
        'mixed',
    ];

    /**
     * @return array<string, float>
     */
    protected function getPaymentMethodTotals(): array
    {
        return once(function (): array {
            $query = Sale::query();
            BranchAuthScope::apply($query);

            $rows = $query
                ->toBase()
                ->selectRaw('payment_method')
                ->selectRaw('SUM(total) as sum_total')
                ->groupBy('payment_method')
                ->get();

            $byKey = [];
            foreach ($rows as $row) {
                $raw = $row->payment_method;
                $key = ($raw === null || trim((string) $raw) === '')
                    ? '__other__'
                    : strtolower(trim((string) $raw));

                $byKey[$key] = ($byKey[$key] ?? 0.0) + (float) $row->sum_total;
            }

            $all = array_sum($byKey);
            $totals = ['all' => $all];

            foreach (self::PAYMENT_METHOD_KEYS as $method) {
                $totals[$method] = (float) ($byKey[$method] ?? 0.0);
            }

            $knownSum = 0.0;
            foreach (self::PAYMENT_METHOD_KEYS as $method) {
                $knownSum += $totals[$method];
            }

            $totals['other'] = max(0.0, $all - $knownSum);

            return $totals;
        });
    }

    protected function formatTabTotalBadge(float $amount): string
    {
        return Number::currency($amount, 'USD', 'en', 2);
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $totals = $this->getPaymentMethodTotals();

        $tabs = [
            'all' => Tab::make('Todas')
                ->icon(Heroicon::Squares2x2)
                ->badge($this->formatTabTotalBadge($totals['all']))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query),
        ];

        /** @var array<string, array{0: string, 1: Heroicon}> */
        $paymentTabMeta = [
            'transfer_usd' => ['Transferencias USD', Heroicon::ArrowsRightLeft],
            'transfer_ves' => ['Transferencia VES', Heroicon::Banknotes],
            'pago_movil' => ['Pago móvil', Heroicon::DevicePhoneMobile],
            'zelle' => ['Zelle', Heroicon::PaperAirplane],
            'efectivo_usd' => ['Efectivo USD', Heroicon::CurrencyDollar],
            'mixed' => ['Pago múltiple', Heroicon::Square2Stack],
        ];

        foreach (self::PAYMENT_METHOD_KEYS as $method) {
            [$label, $icon] = $paymentTabMeta[$method];
            $tabs[$method] = Tab::make($label)
                ->icon($icon)
                ->badge($this->formatTabTotalBadge($totals[$method]))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_method', $method));
        }

        $tabs['other'] = Tab::make('Otros')
            ->icon(Heroicon::EllipsisHorizontalCircle)
            ->badge($this->formatTabTotalBadge($totals['other']))
            ->badgeColor('gray')
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query->where(function (Builder $w): void {
                    $w->whereNull('payment_method')
                        ->orWhere('payment_method', '')
                        ->orWhereNotIn('payment_method', self::PAYMENT_METHOD_KEYS);
                });
            });

        return $tabs;
    }

    public function getTabsContentComponent(): Component
    {
        $tabs = $this->getCachedTabs();

        $tabsComponent = Tabs::make()
            ->label('Totales por forma de pago')
            ->key('resourceTabs')
            ->livewireProperty('activeTab')
            ->contained(false)
            ->extraAttributes(fn (): array => [
                'id' => 'sales-payment-method-tabs',
                'class' => trim('farmadoc-ios-sales-payment-tabs'.(! $this->showSalesPaymentMethodTabs ? ' farmadoc-ios-sales-payment-tabs--tabs-collapsed' : '')),
            ])
            ->tabs($tabs)
            ->hidden(empty($tabs));

        return Group::make([
            View::make('filament.resources.sales.payment-method-tabs-handle')
                ->viewData(fn (): array => [
                    'showSalesPaymentMethodTabs' => $this->showSalesPaymentMethodTabs,
                ]),
            $tabsComponent,
        ])
            ->columns(1)
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'farmadoc-ios-sales-payment-tabs-stack',
            ]);
    }

    /**
     * Registra la acción de caja (carrito) en caché sin mostrarla en la cabecera.
     * No usar ->hidden() en esa acción: en Filament las acciones ocultas se tratan como deshabilitadas
     * y no se pueden montar con replaceMountedAction / mountAction.
     */
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        $this->cacheAction(CashRegisterAction::makeRegister());
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar Venta Directa')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
            CashRegisterAction::makeClientGate(),
        ];
    }
}
