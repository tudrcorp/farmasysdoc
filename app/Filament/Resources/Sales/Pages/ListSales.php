<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\Actions\CashRegisterAction;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Sales\Widgets\StatsListSaleByPaymentMethod;
use App\Filament\Resources\Sales\Widgets\StatsListSaleOverview;
use App\Support\Cash\PhysicalCashBoxBillingGate;
use App\Support\Sales\SalesBillingAccess;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class ListSales extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Listado de Ventas';

    public bool $showSalesStats = true;

    public function mount(): void
    {
        parent::mount();
        $this->showSalesStats = (bool) request()->session()->get(
            $this->salesStatsVisibilitySessionKey(),
            true,
        );

        if (request()->query('abrir') === 'caja' && SaleResource::canViewAny()) {
            if (! PhysicalCashBoxBillingGate::userMayUseCashRegister(Auth::user())) {
                Notification::make()
                    ->title('Caja no disponible')
                    ->body('Debe abrir la caja física antes de usar la caja registradora.')
                    ->warning()
                    ->send();

                return;
            }

            if (! SalesBillingAccess::userCanBill(Auth::user())) {
                Notification::make()
                    ->title('Caja no disponible')
                    ->body('Su rol solo puede consultar el listado y las estadísticas de ventas, no registrar ventas en caja.')
                    ->warning()
                    ->send();

                return;
            }

            $saleTransferId = (int) request()->integer('traslado_venta');
            $prefillFromTransfer = $saleTransferId > 0
                ? CashRegisterAction::prefillArgsFromSaleTransferId($saleTransferId)
                : null;

            if ($saleTransferId > 0 && $prefillFromTransfer === null) {
                Notification::make()
                    ->title('Traslado no disponible para caja')
                    ->body('Solo puede cargar en caja traslados de venta en estado «En proceso» y con ítems válidos.')
                    ->warning()
                    ->send();
            }

            $actionName = CashRegisterAction::REGISTER_ACTION_NAME;
            $actionArgs = is_array($prefillFromTransfer) ? $prefillFromTransfer : [];

            /*
             * Diferir al siguiente tick: la acción de caja debe estar registrada antes de mountAction.
             */
            $this->js(
                'setTimeout(() => $wire.mountAction('
                    .Js::from($actionName)
                    .', '
                    .Js::from($actionArgs)
                    .'), 80)'
            );
        }
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        if (! $this->showSalesStats) {
            return [];
        }

        return [
            StatsListSaleOverview::class,
            StatsListSaleByPaymentMethod::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return ['@sm' => 1, '@md' => 2, '@lg' => 4];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $actions = [];

        if (SaleResource::canCreate()) {
            $actions[] = CreateAction::make()
                ->label('Registrar Venta Directa')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]);
            $actions[] = CashRegisterAction::makeRegister();
        }

        return [
            ...$actions,
            Action::make('toggleSalesStatsVisibility')
                ->label(fn (): string => $this->showSalesStats ? 'Ocultar Stats' : 'Mostrar Stats')
                ->icon(fn (): Heroicon => $this->showSalesStats ? Heroicon::EyeSlash : Heroicon::Eye)
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action',
                ])
                ->action(function (): void {
                    $this->showSalesStats = ! $this->showSalesStats;
                    request()->session()->put(
                        $this->salesStatsVisibilitySessionKey(),
                        $this->showSalesStats,
                    );
                }),
            Action::make('closeSale')
                ->label('Cierre de caja (PDF)')
                ->icon(Heroicon::DocumentArrowDown)
                ->color('danger')
                ->modalHeading('Reporte de cierre de caja')
                ->modalDescription('PDF detallado con todas las ventas completadas del período (fecha efectiva: venta o registro). Por defecto, el día de hoy.')
                ->modalSubmitActionLabel('Descargar PDF')
                ->modalWidth(Width::Medium)
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--danger',
                ])
                ->schema([
                    DatePicker::make('date_from')
                        ->label('Desde')
                        ->default(now())
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    DatePicker::make('date_until')
                        ->label('Hasta')
                        ->default(now())
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data): void {
                    $from = Carbon::parse((string) $data['date_from'])->startOfDay();
                    $until = Carbon::parse((string) $data['date_until'])->endOfDay();

                    if ($until->lt($from)) {
                        Notification::make()
                            ->title('Rango de fechas inválido')
                            ->body('«Hasta» no puede ser anterior a «Desde».')
                            ->danger()
                            ->send();

                        return;
                    }

                    $url = URL::temporarySignedRoute(
                        'sales.cash-close-pdf',
                        now()->addMinutes(10),
                        [
                            'from' => $from->toDateString(),
                            'until' => $until->toDateString(),
                        ]
                    );

                    $this->js('window.open('.Js::from($url).', "_blank")');

                    Notification::make()
                        ->title('Descarga iniciada')
                        ->body('Se abrió una pestaña con el PDF. Si no aparece, permita ventanas emergentes para este sitio.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function salesStatsVisibilitySessionKey(): string
    {
        $userId = Auth::id();

        return is_int($userId)
            ? 'filament.sales.list.show_stats.user.'.$userId
            : 'filament.sales.list.show_stats.guest';
    }
}
