<?php

namespace App\Filament\Resources\AccountsPayables\Actions;

use App\Filament\Resources\AccountsPayables\Support\AccountsPayablePaymentFormSchema;
use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

final class UploadAccountsPayablePaymentProofAction
{
    public const NAME = 'uploadPaymentProof';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(fn (Action $action): string => filled(self::record($action)?->payment_proof_path)
                ? 'Actualizar comprobante'
                : 'Cargar comprobante')
            ->icon(Heroicon::PaperClip)
            ->color('info')
            ->modalWidth(Width::Large)
            ->modalHeading('Comprobante de pago')
            ->modalDescription('Adjunte la imagen o el PDF del comprobante bancario / voucher de la operación. Solo disponible cuando la cuenta está pagada.')
            ->modalSubmitActionLabel('Guardar comprobante')
            ->visible(fn (Action $action): bool => self::record($action)?->status === AccountsPayableStatus::PAGADO)
            ->fillForm(fn (Action $action): array => [
                'payment_proof_path' => self::record($action)?->payment_proof_path,
            ])
            ->schema([
                AccountsPayablePaymentFormSchema::paymentProofUpload(required: true),
            ])
            ->action(function (array $data, Action $action): void {
                $record = self::record($action);
                if ($record === null) {
                    return;
                }

                self::store($record, $data);
            });
    }

    /**
     * @param  array{payment_proof_path?: mixed}  $data
     */
    public static function store(AccountsPayable $record, array $data): void
    {
        $previousPath = $record->payment_proof_path;
        $newPath = filled($data['payment_proof_path'] ?? null)
            ? (string) $data['payment_proof_path']
            : null;

        if ($newPath === null) {
            Notification::make()
                ->title('Comprobante requerido')
                ->body('Debe seleccionar un archivo válido.')
                ->danger()
                ->send();

            return;
        }

        $record->update([
            'payment_proof_path' => $newPath,
        ]);

        if (
            filled($previousPath)
            && $previousPath !== $newPath
            && Storage::disk('public')->exists((string) $previousPath)
        ) {
            Storage::disk('public')->delete((string) $previousPath);
        }

        AuditLogger::record(
            event: 'filament_accounts_payable_payment_proof_uploaded',
            description: 'CxP: se cargó o actualizó el comprobante de pago.',
            auditableType: AccountsPayable::class,
            auditableId: (string) $record->getKey(),
            auditableLabel: $record->supplier_invoice_number,
            properties: [
                'payment_proof_path' => $newPath,
                'replaced_previous' => filled($previousPath) && $previousPath !== $newPath,
            ],
        );

        Notification::make()
            ->title('Comprobante guardado')
            ->body('El archivo quedó asociado a esta cuenta por pagar.')
            ->success()
            ->send();
    }

    private static function record(Action $action): ?AccountsPayable
    {
        $record = $action->getRecord();

        return $record instanceof AccountsPayable ? $record : null;
    }
}
