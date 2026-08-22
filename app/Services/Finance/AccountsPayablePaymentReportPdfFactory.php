<?php

namespace App\Services\Finance;

use App\Models\AccountsPayable;
use App\Models\User;
use App\Support\Finance\AccountsPayablePaymentReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Genera el PDF detallado de un pago a cuenta por pagar (descarga y adjunto de correo).
 */
final class AccountsPayablePaymentReportPdfFactory
{
    public function __construct(
        private readonly AccountsPayablePaymentReportBuilder $builder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(AccountsPayable $accountsPayable, ?User $actor = null): array
    {
        $payload = $this->builder->build($accountsPayable, $actor);

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $payload['pdf_logo_data_uri'] = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $payload['pdf_document_ref'] = strtoupper(substr(hash(
            'sha256',
            (string) $accountsPayable->getKey().'|'.($accountsPayable->paid_at?->toIso8601String() ?? '').'|'.($payload['generated_at'] ?? '')
        ), 0, 10));

        return $payload;
    }

    public function output(AccountsPayable $accountsPayable, ?User $actor = null): string
    {
        return Pdf::loadView('pdf.accounts-payable-payment-report', $this->viewData($accountsPayable, $actor))
            ->setPaper('a4', 'portrait')
            ->output();
    }

    public function download(AccountsPayable $accountsPayable, ?User $actor = null): Response
    {
        return Pdf::loadView('pdf.accounts-payable-payment-report', $this->viewData($accountsPayable, $actor))
            ->setPaper('a4', 'portrait')
            ->download($this->filename($accountsPayable));
    }

    public function filename(AccountsPayable $accountsPayable): string
    {
        $invoice = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            (string) ($accountsPayable->supplier_invoice_number ?: $accountsPayable->getKey()),
        ) ?: 'cxp';

        return 'reporte-pago-cxp-'.$invoice.'.pdf';
    }

    /**
     * @param  Collection<int, AccountsPayable>  $accountsPayables
     * @return array<string, mixed>
     */
    public function viewDataMany(Collection $accountsPayables, ?User $actor = null): array
    {
        $payload = $this->builder->buildMany($accountsPayables, $actor);

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $payload['pdf_logo_data_uri'] = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $ids = $accountsPayables
            ->map(static fn (AccountsPayable $record): int => (int) $record->getKey())
            ->sort()
            ->values()
            ->all();

        $payload['pdf_document_ref'] = strtoupper(substr(hash(
            'sha256',
            implode(',', $ids).'|'.($payload['generated_at'] ?? '').'|'.($payload['generated_by'] ?? '')
        ), 0, 10));

        return $payload;
    }

    /**
     * @param  Collection<int, AccountsPayable>  $accountsPayables
     * @return array{payload: array<string, mixed>, contents: string, filename: string}
     */
    public function renderMany(Collection $accountsPayables, ?User $actor = null): array
    {
        $payload = $this->viewDataMany($accountsPayables, $actor);

        return [
            'payload' => $payload,
            'contents' => Pdf::loadView('pdf.accounts-payable-bulk-payment-report', $payload)
                ->setPaper('a4', 'landscape')
                ->output(),
            'filename' => $this->filenameMany($accountsPayables),
        ];
    }

    /**
     * @param  Collection<int, AccountsPayable>  $accountsPayables
     */
    public function downloadMany(Collection $accountsPayables, ?User $actor = null): Response
    {
        return Pdf::loadView('pdf.accounts-payable-bulk-payment-report', $this->viewDataMany($accountsPayables, $actor))
            ->setPaper('a4', 'landscape')
            ->download($this->filenameMany($accountsPayables));
    }

    /**
     * @param  Collection<int, AccountsPayable>  $accountsPayables
     */
    public function filenameMany(Collection $accountsPayables): string
    {
        $supplier = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            (string) ($accountsPayables->first()?->supplier_name ?: 'proveedor'),
        ) ?: 'proveedor';

        return 'reporte-pagos-cxp-'.mb_substr($supplier, 0, 40).'-'.$accountsPayables->count().'fact.pdf';
    }
}
