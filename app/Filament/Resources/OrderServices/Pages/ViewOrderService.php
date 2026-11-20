<?php

namespace App\Filament\Resources\OrderServices\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ReviewOrderAcceptance;
use App\Filament\Resources\OrderServices\OrderServiceResource;
use App\Models\Order;
use App\Models\OrderService;
use App\Models\User;
use App\Services\Orders\OrderAcceptanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewOrderService extends ViewRecord
{
    protected static string $resource = OrderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reviewAcceptance')
                ->label('Ver detalle del Pedido')
                ->icon(Heroicon::ClipboardDocumentList)
                ->color('primary')
                ->visible(function (): bool {
                    $service = $this->getRecord();

                    if (! $service instanceof OrderService || $service->order_id === null) {
                        return false;
                    }

                    $order = $service->order;

                    return $order instanceof Order && ReviewOrderAcceptance::canOpen($order);
                })
                ->url(function (): string {
                    $service = $this->getRecord();

                    return OrderResource::getUrl('acceptance', ['record' => $service instanceof OrderService ? $service->order_id : null]);
                }),
            Action::make('authorizeShipment')
                ->label('Autorizar envío')
                ->icon(Heroicon::Truck)
                ->color('success')
                ->visible(function (): bool {
                    $service = $this->getRecord();
                    $order = $service instanceof OrderService ? $service->order : null;

                    return $order instanceof Order && $order->status === OrderStatus::Accepted;
                })
                ->requiresConfirmation()
                ->modalHeading('Autorizar envío')
                ->modalDescription('El pedido pasará a Enviado. El aliado verá este estatus y quién autorizó el envío.')
                ->modalSubmitActionLabel('Autorizar envío')
                ->action(function (): void {
                    $service = $this->getRecord();
                    $order = $service instanceof OrderService ? $service->order : null;
                    $user = Auth::user();

                    if (! $order instanceof Order || ! $user instanceof User) {
                        return;
                    }

                    try {
                        app(OrderAcceptanceService::class)->authorizeShipment($order, $user);
                    } catch (ValidationException $exception) {
                        $message = collect($exception->errors())->flatten()->first();

                        Notification::make()
                            ->title('No se pudo autorizar el envío')
                            ->body(is_string($message) ? $message : 'Revise el estado del pedido e intente de nuevo.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $order->refresh();
                    $service->unsetRelation('order');
                    $service->setRelation('order', $order);

                    Notification::make()
                        ->title('Envío autorizado')
                        ->body('El aliado ya ve el pedido como Enviado y quién autorizó el envío.')
                        ->success()
                        ->send();
                }),
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ])
                ->url(route('filament.farmaadmin.resources.order-services.index')),
        ];
    }
}
