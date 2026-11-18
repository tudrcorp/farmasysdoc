<?php

namespace App\Filament\Resources\PartnerAgreementSales\Pages;

use App\Filament\Resources\PartnerAgreementSales\PartnerAgreementSaleResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\PartnerAgreementSale;
use App\Models\User;
use App\Services\Partners\PartnerAgreementSaleService;
use App\Services\Partners\PartnerDeliveryNoteSender;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewPartnerAgreementSale extends ViewRecord
{
    protected static string $resource = PartnerAgreementSaleResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Nota de entrega '.$this->sale()->number;
    }

    public function getSubheading(): ?string
    {
        $sale = $this->sale();

        return 'Venta por convenio · '.($sale->partnerCompany?->legal_name ?? '').' · '.($sale->plan?->name ?? '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Ver / imprimir nota')
                ->icon(Heroicon::Printer)
                ->color('primary')
                ->url(fn (): string => route('partner-agreement-sales.delivery-note', $this->sale()), shouldOpenInNewTab: true),
            Action::make('sendEmail')
                ->label('Enviar por correo')
                ->icon(Heroicon::Envelope)
                ->color('gray')
                ->modalHeading('Enviar nota de entrega por correo')
                ->modalSubmitActionLabel('Enviar')
                ->fillForm(fn (): array => ['email' => PartnerAgreementSaleService::deliverableEmail($this->sale()->client_email)])
                ->schema([
                    TextInput::make('email')->label('Correo del afiliado')->email()->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $result = app(PartnerDeliveryNoteSender::class)->sendEmail($this->sale(), $data['email']);
                    $this->notifySend($result, 'correo');
                }),
            Action::make('sendWhatsApp')
                ->label('Enviar por WhatsApp')
                ->icon(Heroicon::ChatBubbleLeftEllipsis)
                ->color('success')
                ->modalHeading('Enviar nota de entrega por WhatsApp')
                ->modalSubmitActionLabel('Enviar')
                ->fillForm(fn (): array => ['phone' => $this->sale()->client_phone])
                ->schema([
                    TextInput::make('phone')->label('Teléfono del afiliado')->tel()->required()->helperText('Con código de país o en formato 0414-1234567.'),
                ])
                ->action(function (array $data): void {
                    $result = app(PartnerDeliveryNoteSender::class)->sendWhatsApp($this->sale(), $data['phone']);
                    $this->notifySend($result, 'WhatsApp');
                }),
            ActionGroup::make([
                Action::make('backToRegister')
                    ->label('Volver a ventas / caja')
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->url(fn (): string => SaleResource::getUrl('index')),
                Action::make('cancelSale')
                    ->label('Anular nota de entrega')
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->visible(fn (): bool => ! $this->sale()->isCancelled() && Auth::user() instanceof User && Auth::user()->canVoidSales())
                    ->requiresConfirmation()
                    ->modalHeading('Anular nota de entrega')
                    ->modalDescription('Se devuelven los productos al inventario y el monto al fondo del convenio. No se puede deshacer.')
                    ->schema([
                        Textarea::make('reason')->label('Motivo')->required()->maxLength(500)->rows(3),
                    ])
                    ->modalSubmitActionLabel('Anular')
                    ->action(function (array $data): void {
                        $user = Auth::user();

                        if (! $user instanceof User || ! $user->canVoidSales()) {
                            Notification::make()->title('No tiene permiso para anular.')->danger()->send();

                            return;
                        }

                        try {
                            app(PartnerAgreementSaleService::class)->cancel($this->sale(), $user, (string) $data['reason']);
                        } catch (ValidationException $exception) {
                            Notification::make()->title((string) collect($exception->errors())->flatten()->first())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Nota de entrega anulada')->body('Inventario y fondo del convenio restablecidos.')->success()->send();
                        $this->refreshFormData([]);
                        $this->record = $this->sale()->fresh();
                    }),
            ])->label('Más')->icon(Heroicon::EllipsisVertical)->button()->color('gray'),
        ];
    }

    private function sale(): PartnerAgreementSale
    {
        /** @var PartnerAgreementSale $record */
        $record = $this->getRecord();

        return $record;
    }

    /**
     * @param  array{ok: bool, error: ?string, to: ?string}  $result
     */
    private function notifySend(array $result, string $channel): void
    {
        if ($result['ok']) {
            Notification::make()->title('Nota enviada por '.$channel)->body('Destino: '.$result['to'])->success()->send();

            return;
        }

        Notification::make()->title('No se pudo enviar por '.$channel)->body($result['error'])->danger()->send();
    }
}
