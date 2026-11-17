<?php

namespace App\Services\Finance;

use App\Models\AccountsReceivable;
use App\Models\User;
use App\Support\Finance\AccountsReceivableStatus;
use App\Support\Fiscal\CompanyFiscalAddress;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Genera la «Nota de cobro» (documento no fiscal) de una cuenta por cobrar:
 * detalle de la venta a crédito, abonos y saldo pendiente.
 */
final class AccountsReceivableCollectionNotePdfFactory
{
    public function __construct(
        private readonly VenezuelaOfficialUsdVesRateClient $rateClient,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(AccountsReceivable $accountsReceivable, ?User $actor = null): array
    {
        $accountsReceivable->loadMissing(['branch', 'client', 'sale.items.product', 'sale.client']);

        $sale = $accountsReceivable->sale;
        $remainingUsd = round((float) $accountsReceivable->remaining_principal_usd, 2);
        $todayRate = $this->rateClient->rateForDate(now());
        $remainingVesToday = ($todayRate !== null && $todayRate > 0)
            ? round($remainingUsd * $todayRate, 2)
            : null;

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $generatedAt = now();

        return [
            'accounts_receivable' => $accountsReceivable,
            'sale' => $sale,
            'items' => $sale?->items ?? collect(),
            'status_label' => AccountsReceivableStatus::label($accountsReceivable->status),
            'branch_name' => $accountsReceivable->branch?->name ?? '—',
            'client_name' => $accountsReceivable->client_name_snapshot ?: ($sale?->client?->name ?? '—'),
            'client_document' => $accountsReceivable->client_document_snapshot ?: '—',
            'company_name' => (string) config('fiscal.retention_agent.name'),
            'company_rif' => (string) config('fiscal.retention_agent.rif'),
            'company_address' => CompanyFiscalAddress::line(),
            'remaining_usd' => $remainingUsd,
            'today_rate' => $todayRate,
            'remaining_ves_today' => $remainingVesToday,
            'generated_at' => $generatedAt->format('d/m/Y H:i'),
            'generated_by' => $actor?->name ?? 'sistema',
            'pdf_logo_data_uri' => is_readable($logoPath)
                ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
                : null,
            'pdf_document_ref' => strtoupper(substr(hash(
                'sha256',
                (string) $accountsReceivable->getKey().'|'.$generatedAt->toIso8601String(),
            ), 0, 10)),
        ];
    }

    public function download(AccountsReceivable $accountsReceivable, ?User $actor = null): Response
    {
        return Pdf::loadView('pdf.accounts-receivable-collection-note', $this->viewData($accountsReceivable, $actor))
            ->setPaper('a4', 'portrait')
            ->stream($this->filename($accountsReceivable));
    }

    public function filename(AccountsReceivable $accountsReceivable): string
    {
        $saleNumber = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            (string) ($accountsReceivable->sale_number_snapshot ?: $accountsReceivable->getKey()),
        ) ?: 'cxc';

        return 'nota-de-cobro-'.$saleNumber.'.pdf';
    }
}
