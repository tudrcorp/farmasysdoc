<?php

namespace App\Filament\Resources\ProductTransferSales\Pages;

use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class ListProductTransferSales extends ListRecords
{
    protected static string $resource = ProductTransferSaleResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'Traslados de venta';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Ventas internas entre sucursales a costo. No aparecen en el listado de ventas ni en los totales de caja; se gestionan solo aquí.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saleTransferReportPdf')
                ->label('Reporte PDF (rango)')
                ->icon(Heroicon::DocumentArrowDown)
                ->color('gray')
                ->modalHeading('Reporte de traslados de venta')
                ->modalDescription('PDF con los traslados de venta registrados entre dos fechas (por fecha de registro del documento).')
                ->modalSubmitActionLabel('Descargar PDF')
                ->modalWidth(Width::Medium)
                ->schema([
                    DatePicker::make('date_from')
                        ->label('Desde')
                        ->default(now()->startOfMonth())
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
                        'product-transfer-sales.report-pdf',
                        now()->addMinutes(10),
                        [
                            'from' => $from->toDateString(),
                            'until' => $until->toDateString(),
                        ]
                    );

                    $this->js('window.open('.Js::from($url).', "_blank")');

                    Notification::make()
                        ->title('Descarga iniciada')
                        ->body('Se abrió una pestaña con el PDF. Si no aparece, permita ventanas emergentes.')
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary farmadoc-ios-action--liquid-glass',
                ]),
            CreateAction::make()
                ->label('Nuevo traslado de venta')
                ->icon(Heroicon::Plus)
                ->tooltip('Registrar solicitud de envío desde una venta (origen envía, destino recibe).')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary farmadoc-ios-action--liquid-glass',
                ]),
        ];
    }
}
