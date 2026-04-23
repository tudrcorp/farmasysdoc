<?php

namespace App\Filament\Resources\AccountsPayables\Pages;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Models\AccountsPayable;
use App\Services\Finance\AccountsPayablePaymentRegistrar;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ViewAccountsPayable extends ViewRecord
{
    protected static string $resource = AccountsPayableResource::class;

    protected static ?string $title = 'Detalle de cuenta por pagar';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registerAccountsPayablePayment')
                ->label('Registrar pago')
                ->icon(Heroicon::Banknotes)
                ->color('success')
                ->modalHeading('Registrar pago al proveedor')
                ->modalDescription('El movimiento quedará en el histórico de compras (método, forma, fecha y monto) y se actualizará el principal pendiente y el saldo en bolívares según la tasa BCV del día.')
                ->visible(fn (): bool => $this->getRecord() instanceof AccountsPayable
                    && $this->getRecord()->status === AccountsPayableStatus::POR_PAGAR)
                ->schema([
                    Select::make('payment_method')
                        ->label('Método de pago')
                        ->options(PurchaseHistoryPaymentMethod::options())
                        ->required()
                        ->native(false),
                    Select::make('payment_form')
                        ->label('Forma de pago')
                        ->options(PurchaseHistoryPaymentForm::options())
                        ->required()
                        ->native(false),
                    DateTimePicker::make('paid_at')
                        ->label('Fecha y hora del pago')
                        ->seconds(false)
                        ->default(now())
                        ->required(),
                    TextInput::make('amount_paid_ves')
                        ->label('Monto pagado (Bs)')
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->required()
                        ->helperText(function (): string {
                            $r = $this->getRecord();
                            if (! $r instanceof AccountsPayable) {
                                return '';
                            }

                            $usd = (float) ($r->remaining_principal_usd ?? $r->purchase_total_usd);
                            $bs = (float) $r->current_balance_ves;

                            return 'Principal pendiente (USD): '.number_format($usd, 2, ',', '.')
                                .' · Saldo vigente en Bs (último cálculo): '.number_format($bs, 2, ',', '.');
                        }),
                    Textarea::make('notes')
                        ->label('Notas (opcional)')
                        ->rows(2)
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    if (! $record instanceof AccountsPayable) {
                        return;
                    }

                    try {
                        app(AccountsPayablePaymentRegistrar::class)->register($record, $data);
                        $record->refresh();
                        Notification::make()
                            ->title('Pago registrado')
                            ->body('Quedó asentado en el histórico de compras y la cuenta por pagar se actualizó.')
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        $first = collect($e->errors())->flatten()->first();

                        Notification::make()
                            ->title('No se pudo registrar el pago')
                            ->body(is_string($first) ? $first : 'Revise los datos e intente de nuevo.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
