<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\Actions\CashRegisterAction;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Sales\Widgets\StatsListSaleByPaymentMethod;
use App\Filament\Resources\Sales\Widgets\StatsListSaleOverview;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class ListSales extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Listado de Ventas';

    public function mount(): void
    {
        parent::mount();

        if (request()->query('abrir') === 'caja' && SaleResource::canViewAny()) {
            /*
             * Diferir al siguiente tick: las acciones de cabecera (incl. makeClientGate) deben estar
             * registradas en caché antes de mountAction, igual que al pulsar el botón «Caja».
             */
            $this->js(
                'setTimeout(() => $wire.mountAction('.Js::from(CashRegisterAction::CLIENT_GATE_ACTION_NAME).'), 80)'
            );
        }
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
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
     * Registra la acción de caja (carrito) en caché sin mostrarla en la cabecera.
     * No usar ->hidden() en esa acción: en Filament las acciones ocultas se tratan como deshabilitadas
     * y no se pueden montar con replaceMountedAction / mountAction.
     */
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        $this->cacheAction(CashRegisterAction::makeRegister());
    }

    /**
     * @return array<Action>
     */
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
}
