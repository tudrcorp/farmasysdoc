<?php

namespace App\Services\Finance;

use App\Mail\AccountsPayableBulkPaymentReportMail;
use App\Mail\AccountsPayablePaymentReportMail;
use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía al proveedor el PDF del pago registrado en una cuenta por pagar.
 */
final class AccountsPayablePaymentReportMailer
{
    public function __construct(
        private readonly AccountsPayablePaymentReportPdfFactory $pdfFactory,
    ) {}

    public function send(AccountsPayable $accountsPayable): bool
    {
        $email = $accountsPayable->supplierNotificationEmail();
        if ($email === null) {
            AuditLogger::record(
                event: 'accounts_payable_payment_report_mail_skipped',
                description: 'CxP: no se envió el reporte de pago porque el proveedor no tiene correo.',
                auditableType: AccountsPayable::class,
                auditableId: (string) $accountsPayable->getKey(),
                auditableLabel: $accountsPayable->supplier_invoice_number,
            );

            return false;
        }

        try {
            Mail::to($email)->send(new AccountsPayablePaymentReportMail(
                $accountsPayable,
                $this->pdfFactory->output($accountsPayable),
                $this->pdfFactory->filename($accountsPayable),
            ));
        } catch (Throwable $exception) {
            report($exception);
            AuditLogger::record(
                event: 'accounts_payable_payment_report_mail_failed',
                description: 'CxP: falló el envío del reporte de pago al proveedor.',
                auditableType: AccountsPayable::class,
                auditableId: (string) $accountsPayable->getKey(),
                auditableLabel: $accountsPayable->supplier_invoice_number,
                properties: [
                    'supplier_email' => $email,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            );

            throw $exception;
        }

        AuditLogger::record(
            event: 'accounts_payable_payment_report_mail_sent',
            description: 'CxP: se envió el reporte de pago al correo del proveedor.',
            auditableType: AccountsPayable::class,
            auditableId: (string) $accountsPayable->getKey(),
            auditableLabel: $accountsPayable->supplier_invoice_number,
            properties: [
                'supplier_email' => $email,
            ],
        );

        return true;
    }

    /**
     * @param  Collection<int, AccountsPayable>  $accountsPayables
     */
    public function sendMany(Collection $accountsPayables): bool
    {
        $accountsPayables = $accountsPayables
            ->filter(static fn (mixed $record): bool => $record instanceof AccountsPayable)
            ->unique(static fn (AccountsPayable $record): int => (int) $record->getKey())
            ->values();

        $first = $accountsPayables->first();
        if (! $first instanceof AccountsPayable) {
            return false;
        }

        $email = $first->supplierNotificationEmail();
        if ($email === null) {
            AuditLogger::record(
                event: 'accounts_payable_bulk_payment_report_mail_skipped',
                description: 'CxP masivo: no se envió el reporte porque el proveedor no tiene correo.',
                auditableType: AccountsPayable::class,
                auditableId: (string) $first->getKey(),
                auditableLabel: $first->supplier_invoice_number,
                properties: [
                    'accounts_payable_ids' => $accountsPayables->modelKeys(),
                ],
            );

            return false;
        }

        $rendered = $this->pdfFactory->renderMany($accountsPayables);

        try {
            Mail::to($email)->send(new AccountsPayableBulkPaymentReportMail(
                $accountsPayables,
                $rendered['payload'],
                $rendered['contents'],
                $rendered['filename'],
            ));
        } catch (Throwable $exception) {
            report($exception);
            AuditLogger::record(
                event: 'accounts_payable_bulk_payment_report_mail_failed',
                description: 'CxP masivo: falló el envío del reporte de pago al proveedor.',
                auditableType: AccountsPayable::class,
                auditableId: (string) $first->getKey(),
                auditableLabel: $first->supplier_name,
                properties: [
                    'supplier_email' => $email,
                    'accounts_payable_ids' => $accountsPayables->modelKeys(),
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            );

            throw $exception;
        }

        AuditLogger::record(
            event: 'accounts_payable_bulk_payment_report_mail_sent',
            description: 'CxP masivo: se envió el reporte de pago al correo del proveedor.',
            auditableType: AccountsPayable::class,
            auditableId: (string) $first->getKey(),
            auditableLabel: $first->supplier_name,
            properties: [
                'supplier_email' => $email,
                'accounts_payable_ids' => $accountsPayables->modelKeys(),
                'invoice_count' => $accountsPayables->count(),
            ],
        );

        return true;
    }
}
