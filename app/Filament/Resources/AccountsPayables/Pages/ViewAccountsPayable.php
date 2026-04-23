<?php

namespace App\Filament\Resources\AccountsPayables\Pages;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Filament\Resources\AccountsPayables\Support\AccountsPayablePaymentFormSchema;
use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AccountsPayablePaymentRegistrar;
use App\Support\Finance\AccountsPayableStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
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
                ->modalWidth(Width::Large)
                ->modalHeading('Registrar pago al proveedor')
                ->modalDescription('El movimiento quedará en el histórico de compras. Los montos en USD y Bs deben ser coherentes con la tasa BCV oficial del día actual.')
                ->visible(fn (): bool => $this->getRecord() instanceof AccountsPayable
                    && $this->getRecord()->status === AccountsPayableStatus::POR_PAGAR)
                ->fillForm(fn (): array => AccountsPayablePaymentFormSchema::defaultStateForRecord($this->requireAccountsPayableRecord()))
                ->schema(AccountsPayablePaymentFormSchema::paymentFields(true))
                ->action(function (array $data): void {
                    $record = $this->requireAccountsPayableRecord();

                    AuditLogger::record(
                        event: 'filament_accounts_payable_single_payment_submit',
                        description: 'CxP: el usuario envió el formulario de pago desde la vista de detalle.',
                        auditableType: AccountsPayable::class,
                        auditableId: (string) $record->getKey(),
                        auditableLabel: $record->supplier_invoice_number,
                        properties: [
                            'payment_method' => $data['payment_method'] ?? null,
                            'payment_form' => $data['payment_form'] ?? null,
                        ],
                    );

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
                        AuditLogger::record(
                            event: 'filament_accounts_payable_single_payment_validation_failed',
                            description: 'CxP: validación rechazó el pago desde la vista de detalle.',
                            auditableType: AccountsPayable::class,
                            auditableId: (string) $record->getKey(),
                            properties: ['errors' => $e->errors()],
                        );
                        Notification::make()
                            ->title('No se pudo registrar el pago')
                            ->body(is_string($first) ? $first : 'Revise los datos e intente de nuevo.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    private function requireAccountsPayableRecord(): AccountsPayable
    {
        $r = $this->getRecord();
        if (! $r instanceof AccountsPayable) {
            abort(404);
        }

        return $r;
    }
}
